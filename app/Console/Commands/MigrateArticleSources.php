<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Feed;
use App\Services\GoogleNewsUrlDecoder;
use Illuminate\Console\Command;

class MigrateArticleSources extends Command
{
    protected $signature = 'articles:migrate-sources {--decode : Google News URL decode dene (yavaş)}';

    protected $description = 'Mevcut haberler için source_name ve source_url alanlarını doldurur';

    public function handle(GoogleNewsUrlDecoder $decoder): int
    {
        $count = 0;
        $useDecode = (bool) $this->option('decode');

        Article::query()->with('feed')->chunkById(50, function ($articles) use ($decoder, &$count, $useDecode) {
            foreach ($articles as $article) {
                $sourceName = $article->feed instanceof Feed
                    ? ($article->feed->title ?: 'RSS')
                    : 'Manuel';

                $sourceUrl = $useDecode
                    ? $this->resolveSourceUrl($decoder, $article->link)
                    : ($article->link ?: null);

                $originalContent = trim($article->original_title."\n\n".($article->summary ?? ''));

                $article->update([
                    'source_name' => $sourceName,
                    'source_url' => $sourceUrl,
                    'original_content' => $originalContent !== '' ? $originalContent : null,
                ]);

                $count++;
            }
        });

        $this->info("{$count} haber güncellendi.");

        return self::SUCCESS;
    }

    private function resolveSourceUrl(GoogleNewsUrlDecoder $decoder, string $link): ?string
    {
        if ($link === '') {
            return null;
        }

        try {
            $decoded = $decoder->resolve($link);

            return $decoded ?: $link;
        } catch (\Throwable) {
            return $link;
        }
    }
}
