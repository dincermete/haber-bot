<?php

namespace App\Filament\Resources\Articles;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Models\Article;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Haber Havuzu';

    protected static ?string $modelLabel = 'Haber';

    protected static ?string $pluralModelLabel = 'Haber Havuzu';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('type', ArticleType::News)
                ->where('status', '!=', ArticleStatus::Approved))
            ->columns([
                ImageColumn::make('generated_image_path')
                    ->label('Önizleme')
                    ->disk('public')
                    ->height(60)
                    ->square(),
                TextColumn::make('source_name')
                    ->label('Kaynak')
                    ->limit(25)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (Article $record) => $record->title),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (ArticleStatus $state) => $state->label())
                    ->color(fn (ArticleStatus $state) => $state->color()),
                TextColumn::make('created_at')
                    ->label('Eklenme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll(fn (): ?string => Article::query()
                ->where('status', ArticleStatus::Processing)
                ->exists() ? '3s' : null)
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options(collect(ArticleStatus::cases())
                        ->reject(fn (ArticleStatus $s) => $s === ArticleStatus::Approved)
                        ->mapWithKeys(fn (ArticleStatus $s) => [$s->value => $s->label()])),
            ])
            ->recordActions([
                EditAction::make()
                    ->disabled(fn (Article $record) => $record->status === ArticleStatus::Processing)
                    ->tooltip(fn (Article $record) => $record->status === ArticleStatus::Processing
                        ? 'Haber işleniyor, lütfen bekleyin'
                        : null),
                DeleteAction::make()
                    ->disabled(fn (Article $record) => $record->status === ArticleStatus::Processing),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
