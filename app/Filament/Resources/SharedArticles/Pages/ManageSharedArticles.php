<?php

namespace App\Filament\Resources\SharedArticles\Pages;

use App\Filament\Resources\SharedArticles\SharedArticleResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSharedArticles extends ManageRecords
{
    protected static string $resource = SharedArticleResource::class;
}
