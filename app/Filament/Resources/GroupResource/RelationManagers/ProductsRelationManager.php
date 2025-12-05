<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;

class ProductsRelationManager extends RelationManager
{
    // 定义关联名称 (必须与 Group 模型中的方法名 products 一致)
    protected static string $relationship = 'products';

    // 面板标题
    protected static ?string $title = '📦 车队选品配置 (本地库存)';
    
    // 图标
    protected static ?string $icon = 'heroicon-o-shopping-cart';

    // 自定义记录标签
    protected static ?string $modelLabel = '商品';

    /**
     * 这里定义的是“编辑关联数据”时的表单
     * 即：团长想要修改已经添加的商品的“限购”或“售价”时看到的弹窗
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('本地策略设置')
                    ->description('以下设置仅对当前车队生效，不会影响公共商品库。')
                    ->schema([
                        // 1. 本地限购
                        Forms\Components\TextInput::make('limit_per_person')
                            ->label('本车限购 (件)')
                            ->helperText('限制每个成员在本车队能买多少个')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                        
                        // 2. 本地售价
                        Forms\Components\TextInput::make('sell_price')
                            ->label('本车售价 (CNY)')
                            ->helperText('留空则默认使用公共库原价。可用于加价跑腿费。')
                            ->numeric()
                            ->prefix('¥'),
                            
                        // 3. 上下架状态
                        Forms\Components\Toggle::make('is_active')
                            ->label('上架销售')
                            ->helperText('关闭后，该商品将不会在前台显示')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name') // 搜索时匹配的字段
            ->columns([
                // 图片列
                ImageColumn::make('image_url')
                    ->label('图片')
                    ->disk('public')
                    ->square()
                    ->size(50),

                // 商品名称
                TextColumn::make('name')
                    ->label('商品名称')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->name),

                // 公共库原价 (参考用)
                TextColumn::make('price')
                    ->label('库原价')
                    ->money('cny')
                    ->color('gray')
                    ->description(fn ($record) => $record->is_sold_out ? '官网已断货' : ''),

                // 中间表字段：本车实际售价
                TextColumn::make('pivot.sell_price')
                    ->label('本车售价')
                    ->money('cny')
                    ->placeholder('默认') // 如果为空显示“默认”
                    ->sortable(),

                // 中间表字段：本车限购
                TextColumn::make('pivot.limit_per_person')
                    ->label('本车限购')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                
                // 中间表字段：状态
                IconColumn::make('pivot.is_active')
                    ->label('状态')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->alignCenter(),
            ])
            ->filters([
                // 可以添加筛选器，例如只看上架的
                Tables\Filters\Filter::make('active_only')
                    ->label('仅显示上架')
                    ->query(fn (Builder $query) => $query->where('group_product.is_active', true)),
            ])
            ->headerActions([
                // ⭐⭐⭐ 核心功能：从公共库添加商品 (Attach) ⭐⭐⭐
                AttachAction::make()
                    ->label('➕ 从商品库选品')
                    ->color('primary')
                    ->preloadRecordSelect() // 如果商品太多，去掉这行开启AJAX搜索
                    ->recordSelectSearchColumns(['name', 'sku']) // 搜索字段
                    ->form(fn (AttachAction $action): array => [
                        // 第一步：选商品 (系统自动生成)
                        $action->getRecordSelect(), 
                        
                        // 第二步：填写中间表字段
                        Forms\Components\TextInput::make('limit_per_person')
                            ->label('本车限购')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('sell_price')
                            ->label('自定义售价 (可选)')
                            ->numeric()
                            ->prefix('¥'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('立即上架')
                            ->default(true),
                    ]),
            ])
            ->actions([
                // 编辑中间表数据 (调整限购/价格)
                EditAction::make()
                    ->label('调整')
                    ->modalHeading('调整本地库存策略'),
                
                // 从车队中移除该商品 (不会删除公共库商品)
                DetachAction::make()
                    ->label('移除'),
            ])
            ->bulkActions([
                // 批量移除
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}