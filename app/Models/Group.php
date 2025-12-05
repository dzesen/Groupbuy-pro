<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // ⭐ 新增引用
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\User;
use App\Models\Product;

class Group extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_value' => 'integer',
    ];

    // --- 关联关系 ---

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
    
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    // ⭐⭐⭐ 核心修改：多对多关联商品 ⭐⭐⭐
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'group_product')
            ->withPivot(['sell_price', 'limit_per_person', 'is_active']) // 读取中间表字段
            ->withTimestamps();
    }

    // --- 核心业务逻辑 ---

    public function calculateProgress()
    {
        $this->load('members.items');

        $currentAmount = 0;
        $currentQuantity = 0;

        foreach ($this->members as $member) {
            foreach ($member->items as $item) {
                $currentQuantity += $item->quantity;
                // 注意：这里的 price_snapshot 是下单时的快照价格
                $currentAmount += $item->quantity * $item->price_snapshot;
            }
        }

        return [
            'amount' => $currentAmount,
            'quantity' => $currentQuantity,
            'percentage' => $this->target_type === 'amount' 
                ? min(100, ($currentAmount / $this->target_value) * 100)
                : min(100, ($currentQuantity / $this->target_value) * 100)
        ];
    }

    // ⭐⭐⭐ 核心修改：下单逻辑适配本地库存 ⭐⭐⭐
    public function attemptJoin(User $user, array $productsToBuy)
    {
        if ($this->status !== 'building') {
            throw new Exception("车队已锁定或结束，无法加入。");
        }

        $lock = Cache::lock("joining_group_{$this->id}", 5);

        if ($lock->get()) {
            try {
                return DB::transaction(function () use ($user, $productsToBuy) {
                    
                    $member = $this->members()->firstOrCreate(
                        ['user_id' => $user->id],
                        ['total_amount' => 0]
                    );
                    
                    $totalCost = 0;

                    foreach ($productsToBuy as $item) {
                        // 1. 从本车队的关联商品中查找（确保商品已上架）
                        $product = $this->products()
                            ->where('products.id', $item['product_id'])
                            ->where('group_product.is_active', true)
                            ->first();
                        
                        if (!$product) {
                            throw new Exception("商品不在本车队可售列表中");
                        }

                        // 2. 获取团长设置的“本地限购” (存放在 pivot 中)
                        // 如果 pivot 中没设置(为0)，则回退使用商品库的全局限购，或者默认为 1
                        $localLimit = $product->pivot->limit_per_person > 0 
                            ? $product->pivot->limit_per_person 
                            : ($product->limit_per_person > 0 ? $product->limit_per_person : 999);

                        if ($item['quantity'] > $localLimit) {
                            throw new Exception("{$product->name} 本车限购 {$localLimit} 件");
                        }

                        // 3. 获取团长设置的“本地售价”
                        // 如果 pivot.sell_price 有值，则使用它；否则使用全局原价
                        $finalPrice = $product->pivot->sell_price ?? $product->price;

                        $member->items()->create([
                            'product_id' => $product->id,
                            'quantity' => $item['quantity'],
                            'price_snapshot' => $finalPrice // 记录快照价格
                        ]);

                        $totalCost += $finalPrice * $item['quantity'];
                    }

                    $member->increment('total_amount', $totalCost);

                    return $member;
                });

            } finally {
                $lock->release();
            }
        } else {
            throw new Exception("系统繁忙，请重试");
        }
    }
}