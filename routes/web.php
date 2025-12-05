<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopController;

// 首页：跳转到后台登录或特定的活动页
Route::get('/', function () {
    return redirect('/admin'); 
});

// C端用户必须登录才能访问 (使用了 auth 中间件)
Route::middleware(['auth'])->group(function () {
    
    // 1. 展示车队详情页 (用户扫码进入)
    Route::get('/groups/{group}', [ShopController::class, 'show'])->name('groups.show');
    
    // 2. 提交加入车队请求
    Route::post('/groups/{group}/join', [ShopController::class, 'join'])->name('groups.join');
    
    // 3. 简单的成功页面
    Route::get('/success', function () {
        return view('shop.success');
    })->name('shop.success');

    // ⭐⭐⭐ 新增：失败页面路由 ⭐⭐⭐
    Route::get('/failed', function () {
        return view('shop.failed');
    })->name('shop.failed');
});

// 加载 Laravel 默认的认证路由 (由 Breeze/Jetstream 生成)
require __DIR__.'/auth.php';