<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = '活动管理';
    protected static ?string $modelLabel = '活动';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('活动标题')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->label('活动描述')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('start_time')
                    ->label('开始时间')
                    ->required(),
                Forms\Components\DateTimePicker::make('end_time')
                    ->label('结束时间')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('是否启用')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('标题')->searchable(),
                Tables\Columns\TextColumn::make('start_time')->label('开始')->dateTime(),
                Tables\Columns\TextColumn::make('end_time')->label('结束')->dateTime(),
                Tables\Columns\IconColumn::make('is_active')->label('启用')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}