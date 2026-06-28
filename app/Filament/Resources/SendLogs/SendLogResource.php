<?php

namespace App\Filament\Resources\SendLogs;

use App\Filament\Resources\SendLogs\Pages\ManageSendLogs;
use App\Models\SendLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SendLogResource extends Resource
{
    protected static ?string $model = SendLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Log / Geçmiş';

    protected static ?string $modelLabel = 'Log';

    protected static ?string $pluralModelLabel = 'Log / Geçmiş';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('level')
                    ->label('Seviye')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'success' => 'success',
                        'error' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('message')
                    ->label('Mesaj')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('article.title')
                    ->label('Haber')
                    ->limit(30)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('level')
                    ->label('Seviye')
                    ->options([
                        'info' => 'Info',
                        'success' => 'Success',
                        'warning' => 'Warning',
                        'error' => 'Error',
                    ]),
            ])
            ->poll('10s');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSendLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
