<?php

namespace App\Filament\Widgets;

use App\Enums\ArticleType;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingArticlesTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 2,
    ];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Son Onay Bekleyen Haberler')
            ->description('Editör kontrolü bekleyen en güncel kayıtlar')
            ->query(fn (): Builder => Article::query()
                ->pending()
                ->latest('created_at')
                ->limit(10))
            ->columns([
                TextColumn::make('type')
                    ->label('Tür')
                    ->badge()
                    ->formatStateUsing(fn (ArticleType $state) => $state->label())
                    ->color(fn (ArticleType $state) => $state->color()),
                TextColumn::make('source_name')
                    ->label('Kaynak')
                    ->placeholder('—')
                    ->limit(20)
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (Article $record): string => $record->title),
                TextColumn::make('created_at')
                    ->label('Geliş Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Düzenle/Onayla')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->iconButton()
                    ->tooltip('Düzenle / Onayla')
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated(false)
            ->poll('5s')
            ->emptyStateHeading('Onay bekleyen haber yok')
            ->emptyStateDescription('RSS taraması yeni haber bulduğunda burada görünecek.')
            ->emptyStateIcon(Heroicon::OutlinedInbox);
    }
}
