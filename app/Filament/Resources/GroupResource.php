<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupResource\Pages;
use App\Models\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = '车队管理';
    protected static ?string $modelLabel = '车队';

    // ⭐⭐⭐ 这里是新增的表单定义 ⭐⭐⭐
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基础信息')
                    ->schema([
                        // 1. 选择所属活动
                        Forms\Components\Select::make('campaign_id')
                            ->label('所属活动')
                            ->relationship('campaign', 'title') // 关联 Campaign 模型
                            ->required()
                            ->searchable()
                            ->preload(), // 如果没有数据，这里就是空的

                        // 2. 输入车队名称
                        Forms\Components\TextInput::make('name')
                            ->label('车队名称')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('例如：上海徐汇分车'),

                        // 3. 选择团长 (车头)
                        Forms\Components\Select::make('leader_id')
                            ->label('指定车头(团长)')
                            ->relationship('leader', 'name') // 关联 User 模型
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->id()), // 默认选自己
                    ])->columns(2),

                Forms\Components\Section::make('成团条件')
                    ->schema([
                        // 4. 选择目标类型
                        Forms\Components\Select::make('target_type')
                            ->label('成团门槛类型')
                            ->options([
                                'quantity' => '按数量 (件)',
                                'amount' => '按金额 (元)',
                            ])
                            ->required()
                            ->default('quantity'),

                        // 5. 输入目标值
                        Forms\Components\TextInput::make('target_value')
                            ->label('目标数值')
                            ->numeric()
                            ->required()
                            ->suffix('件/元'),
                            
                        // 6. 状态
                        Forms\Components\Select::make('status')
                            ->label('初始状态')
                            ->options([
                                'building' => '拼团中',
                                'locked' => '已锁定',
                            ])
                            ->default('building')
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->label('车号'),
                TextColumn::make('name')->searchable()->label('车队名称'),
                TextColumn::make('campaign.title')->label('所属活动')->limit(20),
                TextColumn::make('leader.name')->label('车头/团长'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'building' => 'warning',
                        'locked' => 'gray',
                        'completed' => 'success',
                        'failed' => 'danger',
                    })
                    ->label('状态'),
                TextColumn::make('target_value')
                    ->label('成团目标')
                    ->formatStateUsing(fn (Group $record) => 
                        $record->target_type === 'amount' 
                        ? "¥{$record->target_value}" 
                        : "{$record->target_value} 件"
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'building' => '拼团中',
                        'locked' => '已锁定',
                        'completed' => '已成团',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            // ⭐⭐⭐ 新增：点击直接跳转到前台拼团页 ⭐⭐⭐
                Action::make('view_frontend')
                    ->label('去前台')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Group $record): string => route('groups.show', $record))
                    ->openUrlInNewTab(), // 在新标签页打开

    // ... 原有的锁定按钮 ...
                Action::make('lockGroup')
                    ->label('锁定')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Group $record) => $record->update(['status' => 'locked']))
                    ->visible(fn (Group $record) => $record->status === 'building'),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
        ];
    }
}