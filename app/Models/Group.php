<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB; // <--- 之前可能也漏了这个
use Exception;

// ⭐⭐⭐ 必须添加下面这两行，否则 PHP 不认识 User 和 Product ⭐⭐⭐
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

    // --- 核心业务逻辑 ---

    public function calculateProgress()
    {
        $this->load('members.items');

        $currentAmount = 0;
        $currentQuantity = 0;

        foreach ($this->members as $member) {
            foreach ($member->items as $item) {
                $currentQuantity += $item->quantity;
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

    public function attemptJoin(User $user, array $productsToBuy)
    {
        if ($this->status !== 'building') {
            throw new Exception("车队已锁定或结束，无法加入。");
        }

        $lock = Cache::lock("joining_group_{$this->id}", 5);

        if ($lock->get()) {
            try {
                return DB::transaction(function () use ($user, $productsToBuy) {
                    
                    // 使用 firstOrCreate 防止重复创建成员记录
                    $member = $this->members()->firstOrCreate(
                        ['user_id' => $user->id],
                        ['total_amount' => 0]
                    );
                    
                    $totalCost = 0;

                    foreach ($productsToBuy as $item) {
                        $product = Product::find($item['product_id']);
                        
                        if (!$product) {
                            throw new Exception("商品不存在");
                        }

                        if ($item['quantity'] > $product->limit_per_person) {
                            throw new Exception("商品 {$product->name} 限购 {$product->limit_per_person} 件");
                        }

                        $member->items()->create([
                            'product_id' => $product->id,
                            'quantity' => $item['quantity'],
                            'price_snapshot' => $product->price
                        ]);

                        $totalCost += $product->price * $item['quantity'];
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
