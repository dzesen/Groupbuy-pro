<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'sku',
        'image_url',
        'price',            // 最终人民币价格 (系统结算用)
        'original_price',   // 原价 (日元)
        'currency',         // 货币单位
        'exchange_rate',    // 汇率
        'limit_per_person',
        'stock_total',
        'external_url', 
        'is_sold_out', 
        'external_id'
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}