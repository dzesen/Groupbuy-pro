<?php

use Illuminate\Foundation\Inspire;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Laravel 默认的一个测试命令 (显示名言)，可以保留
Artisan::command('inspire', function () {
    $this->comment(Inspire::quote());
})->purpose('Display an inspiring quote')->hourly();

// ⭐⭐⭐ 新增：自动爬虫调度 ⭐⭐⭐
// 逻辑：每小时的第 0 分钟自动运行一次 'crawl:chiikawa' 命令
Schedule::command('crawl:chiikawa')->hourly();

// 如果您希望更频繁，比如每 30 分钟一次，可以用:
// Schedule::command('crawl:chiikawa')->everyThirtyMinutes();

// 如果您希望每天凌晨 3 点跑一次 (适合全量同步):
// Schedule::command('crawl:chiikawa')->dailyAt('03:00');