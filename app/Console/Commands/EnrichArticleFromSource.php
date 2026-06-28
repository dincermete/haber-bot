<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\ArticlePageScraper;
use App\Services\GoogleNewsUrlDecoder;
use Illuminate\Console\Command;

class EnrichArticleFromSource extends Command
{
    protected $signature = 'articles:enrich-from-source {--limit=5 : İşlenecek haber sayısı} {--id= : Tek haber ID}';

    protected $description = 'Kaynak web sitesinden özet ve galeri görsellerini çeker';

    public function handle(ArticlePageScraper $scraper, GoogleNewsUrlDecoder $decoder): int
    {
        $query = Article::query()->latest();

        if ($id = $this->option('id')) {
            $query->whereKey($id);
        } else {
            $query->limit(max(1, (int) $this->option('limit')));
        }

        $articles = $query->get();

        if ($articles->isEmpty()) {
            $this->warn('Haber bulunamadı.');

            return self::SUCCESS;
        }

        foreach ($articles as $article) {
            $pageUrl = $article->source_url ?: $article->link;

            if ($decoder->isGoogleNewsUrl($pageUrl)) {
                $pageUrl = $decoder->resolve($pageUrl) ?? $pageUrl;
            }

            $this->info("Scraping: {$pageUrl}");

            $scraped = $scraper->scrape($pageUrl);

            $summary = $article->summary;
            if (mb_strlen($scraped['description']) > mb_strlen((string) $summary)) {
                $summary = $scraped['description'];
            }

            $gallery = array_values(array_unique([
                ...(array) ($article->gallery_images ?? []),
                ...$scraped['images'],
            ]));

            $sourceImage = $article->source_image_url;
            foreach ($scraped['images'] as $img) {
                if (! app(\App\Services\ArticleImageResolver::class)->isLowQualityImageUrl($img)) {
                    $sourceImage = $img;
                    break;
                }
            }

            $article->update([
                'source_url' => str_starts_with($pageUrl, 'http') ? $pageUrl : $article->source_url,
                'summary' => $summary,
                'gallery_images' => $gallery,
                'source_image_url' => $sourceImage ?: $article->source_image_url,
                'original_content' => trim($article->original_title."\n\n".$summary),
            ]);

            $this->line("  özet: ".mb_strlen($summary).' karakter');
            $this->line('  galeri: '.count($gallery).' görsel');
            $this->line('  kapak: '.($sourceImage ?: '—'));
        }

        return self::SUCCESS;
    }
}
