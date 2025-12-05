<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\Campaign;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            // ⭐⭐⭐ 新增：爬虫导入按钮 ⭐⭐⭐
            Actions\Action::make('crawler_import')
                ->label('爬取导入')
                ->icon('heroicon-o-link')
                ->color('info')
                ->form([
                    // 表单：输入链接和选择活动
                    TextInput::make('url')
                        ->label('商品链接 (URL)')
                        ->required()
                        ->url(),
                    Select::make('campaign_id')
                        ->label('导入到哪个活动')
                        ->options(Campaign::all()->pluck('title', 'id'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->crawlAndCreateProduct($data['url'], $data['campaign_id']);
                }),
        ];
    }

    // ⭐⭐⭐ 核心爬虫逻辑 ⭐⭐⭐
    protected function crawlAndCreateProduct($url, $campaignId)
    {
        try {
            // 1. 发起请求
            $client = new Client(['timeout' => 10, 'verify' => false]);
            $response = $client->get($url);
            $html = (string) $response->getBody();

            // 2. 解析 HTML
            $crawler = new Crawler($html);

            // --- 适配逻辑 (这里以通用的 OpenGraph 协议为例) ---
            // 大多数电商网站(淘宝/京东/Chiikawa市场)都会有 og:title, og:image 标签
            // 如果您有特定网站，需要根据该网站修改这里的 CSS 选择器
            
            // 抓取标题
            $title = $crawler->filter('meta[property="og:title"]')->count() > 0 
                ? $crawler->filter('meta[property="og:title"]')->attr('content') 
                : ($crawler->filter('title')->count() > 0 ? $crawler->filter('title')->text() : '未命名商品');

            // 抓取图片 URL
            $imgUrl = $crawler->filter('meta[property="og:image"]')->count() > 0 
                ? $crawler->filter('meta[property="og:image"]')->attr('content') 
                : null;

            // 抓取价格 (尝试抓取常见 meta，抓不到就默认为 0)
            $price = 0;
            // 这是一个比较粗糙的通用尝试，实际需要针对具体网站写正则
            // 例如：$crawler->filter('.price-class')->text(); 
            
            // 3. 下载图片到本地 (防止防盗链失效)
            $localImagePath = null;
            if ($imgUrl) {
                try {
                    $imgContent = $client->get($imgUrl)->getBody();
                    $filename = 'products/' . Str::random(40) . '.jpg';
                    Storage::disk('public')->put($filename, $imgContent);
                    $localImagePath = $filename;
                } catch (\Exception $e) {
                    // 图片下载失败忽略
                }
            }

            // 4. 创建商品入库
            Product::create([
                'campaign_id' => $campaignId,
                'name' => $title,
                'image_url' => $localImagePath,
                'price' => $price, // 价格可能需要手动填
                'limit_per_person' => 1,
                'stock_total' => 999,
            ]);

            Notification::make()
                ->title('爬取成功！')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('爬取失败')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}