<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ArticleImageRegenerator;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class RegenerateArticleImageJob
{
    use Queueable, SerializesModels;

    public function __construct(public Article $article) {}

    public function handle(ArticleImageRegenerator $regenerator): void
    {
        $regenerator->regenerate($this->article);
    }
}
