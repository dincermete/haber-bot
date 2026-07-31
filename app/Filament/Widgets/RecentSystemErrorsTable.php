<?php

namespace App\Filament\Widgets;

use App\Models\SendLog;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentSystemErrorsTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Son Sistem Hataları')
            ->description('Kritik log kayıtları')
            ->query(fn (): Builder => SendLog::query()
                ->whereIn('level', ['error', 'failed'])
                ->latest('created_at')
                ->limit(5))
            ->columns([
                TextColumn::make('message')
                    ->label('Mesaj')
                    ->limit(60)
                    ->wrap()
                    ->tooltip(fn (SendLog $record): string => $record->message),
                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->paginated(false)
            ->poll('10s')
            ->emptyStateHeading('Sistem hatası yok')
            ->emptyStateDescription('Her şey yolunda görünüyor.')
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck);
    }
}
