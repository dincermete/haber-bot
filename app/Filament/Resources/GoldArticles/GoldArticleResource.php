<?php

namespace App\Filament\Resources\GoldArticles;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\GoldArticles\Pages\ListGoldArticles;
use App\Models\Article;
use App\Services\SpecialContentFactory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GoldArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Altın Fiyatları';

    protected static ?string $modelLabel = 'Altın';

    protected static ?string $pluralModelLabel = 'Altın Fiyatları';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'gold-articles';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', ArticleType::Gold)
            ->where('status', '!=', ArticleStatus::Approved);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('generated_image_path')
                    ->label('Önizleme')
                    ->disk('public')
                    ->height(60)
                    ->square(),
                TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('data_fetched_at')
                    ->label('Veri çekimi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (ArticleStatus $state) => $state->label())
                    ->color(fn (ArticleStatus $state) => $state->color()),
            ])
            ->defaultSort('data_fetched_at', 'desc')
            ->emptyStateHeading('Henüz altın kaydı yok')
            ->emptyStateDescription('Güncel veriyi çekmek için üstteki butonu kullanın.')
            ->recordActions([
                EditAction::make()
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoldArticles::route('/'),
        ];
    }

    public static function fetchLatestAction(): Action
    {
        return Action::make('fetchLatest')
            ->label('Güncel Veriyi Çek')
            ->icon(Heroicon::OutlinedArrowPath)
            ->action(function () {
                try {
                    $article = app(SpecialContentFactory::class)->syncGold(auth()->user());

                    Notification::make()
                        ->title('Altın fiyatları güncellendi')
                        ->success()
                        ->send();

                    return redirect(ArticleResource::getUrl('edit', ['record' => $article]));
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Veri çekilemedi')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
