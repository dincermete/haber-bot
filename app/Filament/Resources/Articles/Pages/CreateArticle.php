<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    public function mount(): void
    {
        $this->authorizeAccess();

        $article = Article::query()->create([
            'type' => ArticleType::News,
            'feed_id' => null,
            'article_uid' => hash('sha256', Str::uuid()->toString()),
            'original_title' => '',
            'original_content' => '',
            'title' => '',
            'summary' => '',
            'link' => '',
            'source_name' => 'Manuel',
            'source_url' => null,
            'status' => ArticleStatus::Pending,
        ]);

        $this->redirect(ArticleResource::getUrl('edit', ['record' => $article]));
    }
}
