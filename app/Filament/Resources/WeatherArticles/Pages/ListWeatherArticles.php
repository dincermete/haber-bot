<?php

namespace App\Filament\Resources\WeatherArticles\Pages;

use App\Filament\Resources\WeatherArticles\WeatherArticleResource;
use Filament\Resources\Pages\ListRecords;

class ListWeatherArticles extends ListRecords
{
    protected static string $resource = WeatherArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            WeatherArticleResource::fetchLatestAction(),
        ];
    }
}
