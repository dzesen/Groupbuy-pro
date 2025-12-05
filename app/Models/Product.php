<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // ⭐ 新增引用

class Product extends Model
{
    // 允许批量赋值的字段
    protected $fillable = [
        'campaign_id',
        'name',
        'sku',
        'image_url',
        
        // 价格相关
        'price',            // 最终人民币售价
        'original_price',   // 原币价格
        'currency',         // 币种
        'exchange_rate',    // 汇率
        
        // 库存与爬虫状态
        'limit_per_person', // 全局默认限购
        'stock_total',      // 全局库存
        'is_sold_out',      // 是否售罄
        'external_url',     // 来源链接
        'external_id',      // 外部ID
    ];

    protected $casts = [
        'is_sold_out' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    // ⭐⭐⭐ 新增：多对多关联车队 ⭐⭐⭐
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_product')
            ->withPivot(['sell_price', 'limit_per_person', 'is_active'])
            ->withTimestamps();
    }
}