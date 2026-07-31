<?php

namespace App\Filament\Widgets;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ArticlePipelineStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '5s';

    protected static bool $isLazy = false;

    protected ?string $heading = 'Haber Hattı';

    protected ?string $description = 'RSS akışı ve onay sürecinin anlık özeti';

    protected int | array | null $columns = 4;

    protected function getStats(): array
    {
        return [
            Stat::make('İşleniyor (Processing)', Article::query()->processing()->count())
                ->description('RSS’ten işlenen haberler')
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->color('info')
                ->icon(Heroicon::OutlinedArrowPath)
                ->url(ArticleResource::getUrl('index', ['tableFilters' => ['status' => ['value' => ArticleStatus::Processing->value]]])),

            Stat::make('Onay Bekleyen (Pending)', Article::query()->pending()->count())
                ->description('Editör onayı bekliyor')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning')
                ->icon(Heroicon::OutlinedClock)
                ->url(ArticleResource::getUrl('index', ['tableFilters' => ['status' => ['value' => ArticleStatus::Pending->value]]])),

            Stat::make('Üretilen (Sent)', Article::query()->whereNotNull('generated_image_path')->count())
                ->description('Görseli üretilmiş haberler')
                ->descriptionIcon(Heroicon::OutlinedPhoto)
                ->color('success')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->url(ArticleResource::getUrl('index')),

            Stat::make('Hatalı (Failed)', Article::query()->failed()->count())
                ->description('AI veya sistem hatası')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->icon(Heroicon::OutlinedXCircle)
                ->url(ArticleResource::getUrl('index', ['tableFilters' => ['status' => ['value' => ArticleStatus::Failed->value]]])),
        ];
    }
}
