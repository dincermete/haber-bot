<?php

namespace App\Filament\Resources\SharedArticles;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\SharedArticles\Pages\ManageSharedArticles;
use App\Models\Article;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SharedArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Üretilen Haberler';

    protected static ?string $modelLabel = 'Üretilen Haber';

    protected static ?string $pluralModelLabel = 'Üretilen Haberler';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'archive';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', ArticleStatus::Approved)
            ->whereNotNull('generated_image_path');
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
                TextColumn::make('source_name')
                    ->label('Kaynak')
                    ->limit(25),
                TextColumn::make('edited_at')
                    ->label('Son düzenleme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('approved_at')
                    ->label('Onaylanma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('approved_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->label('Düzenle')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),
                Action::make('download')
                    ->label('İndir')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function (Article $record): ?BinaryFileResponse {
                        $path = $record->generated_image_path;
                        if (! $path) {
                            return null;
                        }

                        $fullPath = Storage::disk('public')->path($path);
                        if (! is_file($fullPath)) {
                            return null;
                        }

                        $slug = Str::slug(Str::limit((string) ($record->title ?? 'gorsel'), 40, '')) ?: 'gorsel';
                        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'png');

                        return response()->download(
                            $fullPath,
                            "haber-{$record->id}-{$slug}.{$extension}",
                        );
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSharedArticles::route('/'),
        ];
    }
}
