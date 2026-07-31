<?php

namespace App\Console\Commands;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Jobs\SendTelegramNewArticleJob;
use App\Models\Article;
use App\Models\Feed;
use App\Services\ActivityLogger;
use App\Services\ArticleFilterService;
use App\Services\ArticleImageResolver;
use App\Services\RssFeedService;
use Illuminate\Console\Command;

class CheckRssFeeds extends Command
{
    protected $signature = 'rss:check';

    protected $description = 'Aktif RSS kaynaklarını tarar ve yeni haberleri işler';

    public function handle(
        RssFeedService $rssFeedService,
        ArticleFilterService $filterService,
        ArticleImageResolver $imageResolver,
        ActivityLogger $logger,
    ): int {
        Article::query()
            ->processing()
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update([
                'status' => ArticleStatus::Failed,
                'error_message' => 'RSS işleme zaman aşımına uğradı.',
            ]);

        $feeds = Feed::query()->where('is_active', true)->get();

        if ($feeds->isEmpty()) {
            $this->warn('Taranacak RSS kaynağı bulunamadı.');
            $logger->log('Taranacak RSS kaynağı bulunamadı.', 'warning');

            return self::SUCCESS;
        }

        $totalNew = 0;

        foreach ($feeds as $feed) {
            try {
                $result = $rssFeedService->fetch($feed->url, $feed->title);

                if (! empty($result['title']) && $result['title'] !== $feed->title) {
                    $feed->update(['title' => $result['title']]);
                }
            } catch (\Throwable $e) {
                $this->error("{$feed->url} taranamadı: {$e->getMessage()}");
                $logger->log("{$feed->title} taranamadı: {$e->getMessage()}", 'error');

                continue;
            }

            $newCount = 0;

            foreach ($result['articles'] as $item) {
                if (Article::query()->where('article_uid', $item['article_uid'])->exists()) {
                    continue;
                }

                $article = Article::create([
                    'type' => ArticleType::News,
                    'feed_id' => $feed->id,
                    'article_uid' => $item['article_uid'],
                    'original_title' => $item['title'],
                    'title' => $item['title'],
                    'summary' => $item['summary'] ?? null,
                    'link' => $item['link'],
                    'source_name' => $item['source_name'] ?? $feed->title,
                    'source_url' => $item['source_url'] ?? $item['link'],
                    'status' => ArticleStatus::Processing,
                ]);

                try {
                    $item = $rssFeedService->enrichItem($item);

                    $sourceImageUrl = $item['source_image_url'] ?: null;
                    if (! $sourceImageUrl) {
                        $resolveLink = $item['source_url'] ?? $item['link'];
                        $sourceImageUrl = $imageResolver->resolve(null, $resolveLink) ?: null;
                    }

                    $article->update([
                        'original_content' => $item['original_content'] ?? null,
                        'title' => $item['title'],
                        'summary' => $item['summary'],
                        'link' => $item['link'],
                        'source_name' => $item['source_name'] ?? $feed->title,
                        'source_url' => $item['source_url'] ?? $item['link'],
                        'gallery_images' => $item['gallery_images'] ?? [],
                        'source_image_url' => $sourceImageUrl,
                        'status' => ArticleStatus::Pending,
                    ]);

                    if (! $filterService->shouldSend($article->fresh())) {
                        $article->update(['status' => ArticleStatus::Filtered]);
                        $logger->log("Filtrelendi: {$article->title}", 'info', $article);

                        continue;
                    }

                    $newCount++;
                    $totalNew++;
                    $logger->log("Onay bekliyor: {$article->title}", 'info', $article);

                    SendTelegramNewArticleJob::dispatchSync($article->fresh());
                } catch (\Throwable $e) {
                    $article->update([
                        'status' => ArticleStatus::Failed,
                        'error_message' => $e->getMessage(),
                    ]);

                    $logger->log("İşlenemedi: {$article->title} — {$e->getMessage()}", 'error', $article);
                }
            }

            $this->info("{$feed->title} tarandı, {$newCount} yeni haber.");
            $logger->log("{$feed->title} tarandı, {$newCount} yeni haber bulundu.", 'info');
        }

        $logger->log("Tarama tamamlandı. Toplam {$totalNew} yeni haber.", 'success');

        return self::SUCCESS;
    }
}
