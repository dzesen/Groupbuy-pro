<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Campaign;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class CrawlChiikawa extends Command
{
    // 命令签名
    protected $signature = 'crawl:chiikawa {--pages=5 : 爬取多少页}';
    protected $description = '通过 Node.js 脚本爬取 Chiikawa Market (最终稳定版)';

    // 目标基础 URL
    protected $baseUrl = 'https://chiikawamarket.jp/products.json';

    public function handle()
    {
        $this->info("🚀 启动混合爬虫引擎 (PHP + Node.js)...");

        // 1. 自动寻找 Node 路径
        // 如果您的 node 在 /usr/local/bin/node，这里会自动找到
        $nodePath = trim(shell_exec('which node'));
        if (empty($nodePath)) {
            // 备用路径，防止 shell_exec 被禁用
            $nodePath = '/usr/bin/node';
        }
        
        $this->info("Node 环境: $nodePath");

        // 脚本路径
        $scriptPath = base_path('crawler.js');

        if (!file_exists($scriptPath)) {
            $this->error("❌ 未找到 crawler.js 脚本，请确认它在项目根目录下！");
            return;
        }

        // 创建默认活动
        $campaign = Campaign::firstOrCreate(
            ['title' => 'Chiikawa 官网自动采集'],
            ['start_time' => now(), 'end_time' => now()->addYear(), 'is_active' => true]
        );

        $totalPages = $this->option('pages');
        $count = 0;

        // 循环分页抓取
        for ($page = 1; $page <= $totalPages; $page++) {
            $this->info("正在请求第 {$page} 页...");
            
            try {
                $targetUrl = $this->baseUrl . "?limit=250&page={$page}";

                // 2. 调用 Node 脚本
                $process = new Process([$nodePath, $scriptPath, $targetUrl]);
                $process->setTimeout(120); // 给足超时时间
                
                // ⭐⭐⭐ 核心配置：注入环境变量 ⭐⭐⭐
                // 解决 www-data 用户没有 HOME 目录导致 Chrome 崩溃的问题
                $process->setEnv([
                    'HOME' => base_path('storage/app'), 
                    'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                    'PUPPETEER_CACHE_DIR' => base_path('storage/app/.puppeteer_cache'), 
                ]);

                $process->run();

                // 3. 检查 Node 运行结果
                if (!$process->isSuccessful()) {
                    $this->error("❌ Node 脚本运行失败");
                    $this->error("退出码: " . $process->getExitCode());
                    $this->error("错误输出: " . $process->getErrorOutput());
                    continue; // 跳过这一页
                }

                $jsonString = $process->getOutput();
                $jsonString = trim($jsonString);
                
                // 4. 解析 JSON
                $data = json_decode($jsonString, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error("❌ JSON 解析失败。可能被拦截或返回了 HTML。");
                    $this->line("返回内容前100字符: " . Str::limit($jsonString, 100));
                    break; 
                }

                $products = $data['products'] ?? [];

                if (empty($products)) {
                    $this->info("没有更多商品了，停止抓取。");
                    break;
                }

                // 5. 处理商品入库
                foreach ($products as $item) {
                    $this->processProduct($item, $campaign->id);
                    $count++;
                }

                // 礼貌爬虫：休息一下
                sleep(3);

            } catch (\Exception $e) {
                $this->error("❌ PHP 系统错误: " . $e->getMessage());
            }
        }

        $this->info("🎉 爬取完成！本次共同步 {$count} 个商品。");
    }

    protected function processProduct($item, $campaignId)
    {
        $productUrl = 'https://chiikawamarket.jp/products/' . $item['handle'];
        
        $isSoldOut = true;
        $price = 0;
        
        // 检查变体库存和价格
        if (!empty($item['variants'])) {
            foreach ($item['variants'] as $variant) {
                if ($variant['available']) {
                    $isSoldOut = false;
                }
                $price = $variant['price'];
            }
        }

        // 更新或创建商品
        $product = Product::updateOrCreate(
            ['external_id' => (string)$item['id']], 
            [
                'campaign_id' => $campaignId,
                'name' => $item['title'],
                'external_url' => $productUrl,
                'original_price' => $price,
                'currency' => 'JPY',
                'exchange_rate' => 0.055, // 默认汇率
                'price' => round($price * 0.055, 2), // 自动算 CNY
                'is_sold_out' => $isSoldOut, 
                // 如果是补货（之前是0现在有货），这里可以恢复库存逻辑，或者简单处理
                'stock_total' => $isSoldOut ? 0 : 999, 
                'limit_per_person' => 1,
            ]
        );

        // 图片同步 (如果本地没图，尝试下载)
        if (empty($product->image_url) && !empty($item['images'][0]['src'])) {
            $this->downloadImage($product, $item['images'][0]['src']);
        }
        
        $status = $isSoldOut ? "❌ 售罄" : "✅ 在售";
        $this->line("同步: [{$item['title']}] - {$status}");
    }

    // ⭐⭐⭐ 修复版图片下载逻辑 ⭐⭐⭐
    protected function downloadImage($product, $url)
    {
        try {
            // 1. 自动补全 URL 协议 (Shopify 经常返回 //cdn.shopify.com)
            if (str_starts_with($url, '//')) {
                $url = 'https:' . $url;
            }

            // 2. 打印日志 (方便调试)
            // $this->line("  📷 正在下载图片...");

            // 3. 发起请求
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])
            ->timeout(30)
            ->withoutVerifying()
            ->get($url);

            if ($response->failed()) {
                $this->error("  ❌ 图片下载失败 (HTTP " . $response->status() . ")");
                return;
            }

            // 4. 保存文件
            $name = 'products/' . Str::random(40) . '.jpg';
            Storage::disk('public')->put($name, $response->body());
            
            // 5. 更新数据库
            $product->update(['image_url' => $name]);
            
            $this->info("  ✅ 图片保存成功");

        } catch (\Exception $e) {
            $this->error("  ❌ 图片异常: " . $e->getMessage());
        }
    }
}