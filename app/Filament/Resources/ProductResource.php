<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = '商品库';
    protected static ?string $modelLabel = '商品';

    // 只有管理员能看到
    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')->schema([
                    Forms\Components\Select::make('campaign_id')
                        ->label('所属活动')
                        ->relationship('campaign', 'title')
                        ->searchable()
                        ->preload()
                        ->required(),
                        
                    Forms\Components\TextInput::make('name')
                        ->label('商品名称')
                        ->required(),

                    FileUpload::make('image_url')
                        ->label('商品图片')
                        ->image()
                        ->disk('public')
                        ->directory('products')
                        ->visibility('public')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sku')
                        ->label('SKU / 货号'),
                    
                    // ⭐ 新增：来源链接 (爬虫自动填入)
                    Forms\Components\TextInput::make('external_url')
                        ->label('来源链接')
                        ->url()
                        ->suffixIcon('heroicon-m-globe-alt')
                        ->columnSpanFull(),
                ])->columns(2),

                Forms\Components\Section::make('价格计算')->schema([
                    Forms\Components\TextInput::make('original_price')
                        ->label('原币价格')
                        ->numeric()
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            self::updatePrice($get, $set);
                        }),

                    Forms\Components\Select::make('currency')
                        ->label('货币')
                        ->options(['JPY'=>'日元', 'CNY'=>'人民币', 'USD'=>'美元'])
                        ->default('JPY')
                        ->required(),

                    Forms\Components\TextInput::make('exchange_rate')
                        ->label('汇率')
                        ->numeric()
                        ->default(0.055)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            self::updatePrice($get, $set);
                        }),

                    Forms\Components\TextInput::make('price')
                        ->label('最终售价 (CNY)')
                        ->prefix('¥')
                        ->numeric()
                        ->required(),
                ])->columns(4),

                Forms\Components\Section::make('库存与状态')->schema([
                    Forms\Components\TextInput::make('limit_per_person')
                        ->label('限购')
                        ->numeric()
                        ->default(1),
                        
                    Forms\Components\TextInput::make('stock_total')
                        ->label('库存')
                        ->numeric()
                        ->default(9999),
                    
                    // ⭐ 新增：售罄状态开关 (支持手动修改)
                    Forms\Components\Toggle::make('is_sold_out')
                        ->label('已售罄')
                        ->onColor('danger')
                        ->offColor('success')
                        ->default(false),
                ])->columns(3),
            ]);
    }

    protected static function updatePrice(Get $get, Set $set): void
    {
        $original = floatval($get('original_price'));
        $rate = floatval($get('exchange_rate'));
        if ($original > 0 && $rate > 0) {
            $set('price', round($original * $rate, 2));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('图片')
                    ->disk('public')
                    ->square()
                    ->size(50),

                Tables\Columns\TextColumn::make('name')
                    ->label('商品名称')
                    ->searchable()
                    ->limit(20)
                    ->description(fn (Product $record) => $record->is_sold_out ? '❌ 官网已断货' : ''),

                // ⭐ 新增：售罄状态图标列
                IconColumn::make('is_sold_out')
                    ->label('状态')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle') // 售罄显示 X
                    ->falseIcon('heroicon-o-check-circle') // 有货显示 √
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('price')
                    ->label('售价')
                    ->money('cny')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_total')
                    ->label('本地库存')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('campaign_id')
                    ->relationship('campaign', 'title'),

                // ⭐ 新增：按库存状态筛选
                TernaryFilter::make('is_sold_out')
                    ->label('货源状态')
                    ->placeholder('全部商品')
                    ->trueLabel('❌ 已售罄')
                    ->falseLabel('✅ 有货'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // 方便点击跳转到官网查看
                Tables\Actions\Action::make('visit')
                    ->label('官网')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Product $record) => $record->external_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Product $record) => $record->external_url),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}