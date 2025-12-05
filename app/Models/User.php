<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // 确保有这个
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // 允许管理员和白名单邮箱进入
        return $this->isAdmin() || in_array($this->email, [
            'dzess@qq.com', // 您的邮箱
        ]);
    }

    // ⭐⭐⭐ 必须添加这个方法，否则 ProductResource 会报错 500 ⭐⭐⭐
    public function isAdmin(): bool
    {
        // 如果您还没有 role 字段，这里暂时简单粗暴地检查邮箱
        // 等数据库迁移加了 role 字段后，可以改为: return $this->role === 'admin';
        
        return in_array($this->email, ['dzess@qq.com']) || $this->role === 'admin';
    }
}