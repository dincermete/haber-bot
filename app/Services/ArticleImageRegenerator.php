<?php

namespace App\Services;

use App\Models\Article;

class ArticleImageRegenerator
{
    public function __construct(
        private readonly ImageGeneratorService $imageGenerator,
        private readonly ActivityLogger $logger,
    ) {}

    public function regenerate(Article $article): string
    {
        $article = $article->fresh();

        try {
            $path = $this->imageGenerator->generate($article);
            $article->update(['generated_image_path' => $path]);
            $this->logger->log("Görsel üretildi: {$article->title}", 'info', $article);

            return $path;
        } catch (\Throwable $e) {
            $this->logger->log('Görsel üretilemedi: '.$e->getMessage(), 'error', $article);
            throw $e;
        }
    }
}
