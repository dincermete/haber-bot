<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ActivityLogger;
use App\Services\TelegramService;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SendTelegramNewArticleJob
{
    use Queueable, SerializesModels;

    public function __construct(public Article $article) {}

    public function handle(TelegramService $telegram, ActivityLogger $logger): void
    {
        if (! $telegram->isEnabled()) {
            return;
        }

        $article = $this->article->fresh();

        if (! $article || $article->telegram_sent_at !== null) {
            return;
        }

        try {
            $telegram->notifyNewArticle($article);
            $article->update(['telegram_sent_at' => now()]);
            $logger->log("Telegram'a gönderildi: {$article->title}", 'success', $article);
        } catch (\Throwable $e) {
            $logger->log('Telegram hatası: '.$e->getMessage(), 'error', $article);
            throw $e;
        }
    }
}
