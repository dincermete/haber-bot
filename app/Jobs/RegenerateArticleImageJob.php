<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ActivityLogger;
use App\Services\ImageGeneratorService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegenerateArticleImageJob implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public Article $article) {}

    public function uniqueId(): string
    {
        return 'regenerate-article-'.$this->article->id;
    }

    public function handle(ImageGeneratorService $imageGenerator, ActivityLogger $logger): void
    {
        $article = $this->article->fresh();

        try {
            $path = $imageGenerator->generate($article);
            $article->update(['generated_image_path' => $path]);
            $logger->log("Görsel yeniden üretildi: {$article->title}", 'info', $article);
        } catch (\Throwable $e) {
            $logger->log('Görsel üretilemedi: '.$e->getMessage(), 'error', $article);
            throw $e;
        }
    }
}
