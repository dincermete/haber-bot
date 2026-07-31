<?php

namespace App\Filament\Resources\GoldArticles\Pages;

use App\Filament\Resources\GoldArticles\GoldArticleResource;
use Filament\Resources\Pages\ListRecords;

class ListGoldArticles extends ListRecords
{
    protected static string $resource = GoldArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GoldArticleResource::fetchLatestAction(),
        ];
    }
}
