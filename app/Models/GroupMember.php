<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupMember extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    // ⭐⭐⭐ 必须有这个方法，否则前台进度条计算会报错 500 ⭐⭐⭐
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}