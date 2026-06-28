<?php

namespace App\Services;

use SimplePie\SimplePie;

class RssFeedService
{
    public function __construct(
        private readonly ArticleImageResolver $imageResolver,
        private readonly GoogleNewsUrlDecoder $urlDecoder,
        private readonly ArticlePageScraper $pageScraper,
    ) {}

    public function fetch(string $url, string $feedTitle = ''): array
    {
        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->withHeaders(['User-Agent' => 'HaberBot/1.0 RSS Reader'])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Bağlantı hatası: HTTP {$response->status()}");
        }

        $raw = $this->sanitizeXml($response->body());
        $this->assertIsFeed($raw, $url);

        $feed = new SimplePie;
        $feed->set_raw_data($raw);
        $feed->enable_cache(false);
        $feed->init();

        if ($feed->error()) {
            throw new \RuntimeException('RSS parse hatası: '.$feed->error());
        }

        $parsedFeedTitle = trim(strip_tags((string) $feed->get_title()));
        $feedTitle = $parsedFeedTitle !== '' ? $parsedFeedTitle : $feedTitle;
        $articles = [];

        foreach ($feed->get_items() ?? [] as $item) {
            $uid = $this->makeUid($item);
            if (! $uid) {
                continue;
            }

            $link = trim((string) $item->get_link());
            $galleryImages = $this->extractGalleryImages($item, $link);
            $sourceName = $this->buildSourceName($feedTitle, $item);
            $imageUrl = $this->pickBestImage($galleryImages, $link);
            $title = $this->stripHtml($item->get_title() ?: 'Başlıksız');
            $summary = $this->extractSummary($item);

            $articles[] = [
                'article_uid' => $uid,
                'title' => $title,
                'summary' => $summary,
                'link' => $link,
                'source_name' => $sourceName,
                'source_url' => null,
                'gallery_images' => $galleryImages,
                'source_image_url' => $imageUrl,
                'original_content' => trim($title."\n\n".$summary),
            ];
        }

        return ['title' => $feedTitle, 'articles' => $articles];
    }

    /**
     * Google News decode + kaynak site scrape (yalnızca yeni haberler için).
     */
    public function enrichItem(array $item): array
    {
        $link = $item['link'] ?? '';
        $sourceUrl = $this->resolveSourceUrl($link);
        $pageUrl = $sourceUrl ?: $link;

        $item['source_url'] = $sourceUrl;

        $summary = (string) ($item['summary'] ?? '');
        $galleryImages = (array) ($item['gallery_images'] ?? []);
        $imageUrl = (string) ($item['source_image_url'] ?? '');

        if ($this->pageScraper->needsEnrichment($summary, $galleryImages, $imageUrl ?: null, $pageUrl)) {
            $scraped = $this->pageScraper->scrape($pageUrl);

            if ($summary === '' && $scraped['description'] !== '') {
                $summary = $scraped['description'];
            } elseif (mb_strlen($summary) < 80 && mb_strlen($scraped['description']) > mb_strlen($summary)) {
                $summary = $scraped['description'];
            }

            if ($scraped['images'] !== []) {
                $galleryImages = array_values(array_unique([...$galleryImages, ...$scraped['images']]));
                $imageUrl = $this->pickBestImage($galleryImages, $pageUrl) ?: $imageUrl;
            }
        }

        $item['summary'] = $summary;
        $item['gallery_images'] = $galleryImages;
        $item['source_image_url'] = $imageUrl;
        $item['original_content'] = trim(($item['title'] ?? '')."\n\n".$summary);
        $item['source_url'] = $sourceUrl ?: $link;

        return $item;
    }

    public function resolveSourceUrl(string $link): ?string
    {
        $link = trim($link);
        if ($link === '') {
            return null;
        }

        try {
            $decoded = $this->urlDecoder->resolve($link);

            return $decoded ?: $link;
        } catch (\Throwable) {
            return $link;
        }
    }

    private function buildSourceName(string $feedTitle, $item): string
    {
        $source = trim(strip_tags((string) $item->get_source()?->get_name()));

        if ($source !== '' && $feedTitle !== '') {
            return $feedTitle.' - '.$source;
        }

        if ($source !== '') {
            return $source;
        }

        return $feedTitle !== '' ? $feedTitle : 'RSS';
    }

    private function extractGalleryImages($item, string $articleLink): array
    {
        $candidates = [];

        if ($enclosure = $item->get_enclosure()) {
            $link = trim((string) $enclosure->get_link());
            $type = strtolower((string) $enclosure->get_type());
            if ($link && ($type === '' || str_starts_with($type, 'image/') || $type === 'image')) {
                $candidates[] = $link;
            }
        }

        foreach ([
            [SimplePie::NAMESPACE_MEDIARSS, 'content'],
            [SimplePie::NAMESPACE_MEDIARSS, 'thumbnail'],
            [SimplePie::NAMESPACE_MEDIARSS_WRONG, 'content'],
            [SimplePie::NAMESPACE_MEDIARSS_WRONG, 'thumbnail'],
        ] as [$namespace, $tag]) {
            foreach ((array) ($item->get_item_tags($namespace, $tag) ?? []) as $mediaTag) {
                $url = trim((string) ($mediaTag['attribs']['']['url'] ?? ''));
                if ($url !== '') {
                    $candidates[] = $url;
                }
            }
        }

        $content = (string) ($item->get_content() ?: $item->get_description());
        if (preg_match_all('/<img[^>]+(?:data-src|data-lazy-src|src)=["\']([^"\']+)["\']/i', $content, $matches)) {
            foreach ($matches[1] as $src) {
                $candidates[] = trim($src);
            }
        }

        $unique = [];
        foreach ($candidates as $candidate) {
            $normalized = $this->imageResolver->normalizeUrl($candidate, $articleLink);
            if ($normalized && ! in_array($normalized, $unique, true)) {
                $unique[] = $normalized;
            }
        }

        return $unique;
    }

    private function pickBestImage(array $galleryImages, string $articleLink): string
    {
        foreach ($galleryImages as $url) {
            if (! $this->imageResolver->isLowQualityImageUrl($url)) {
                return $url;
            }
        }

        return $galleryImages[0] ?? '';
    }

    private function assertIsFeed(string $body, string $url): void
    {
        $start = strtolower(ltrim($body));

        if (str_starts_with($start, '<!doctype html') || str_starts_with($start, '<html')) {
            throw new \RuntimeException(
                'Kaynak HTML sayfası döndürdü; geçerli RSS/Atom feed değil. Feed URL\'sini kontrol edin.'
            );
        }

        if (! str_contains($start, '<rss') && ! str_contains($start, '<feed') && ! str_contains($start, '<rdf:rdf')) {
            throw new \RuntimeException(
                'Yanıt geçerli bir RSS/Atom formatında değil. Feed URL\'sini kontrol edin.'
            );
        }
    }

    private function sanitizeXml(string $data): string
    {
        $data = preg_replace('/^\xEF\xBB\xBF/', '', $data) ?? $data;
        $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data) ?? $data;

        return $data;
    }

    private function makeUid($item): ?string
    {
        $id = trim((string) $item->get_id());
        if ($id !== '') {
            return $id;
        }

        $link = trim((string) $item->get_link());
        if ($link !== '') {
            return hash('sha256', $link);
        }

        return null;
    }

    private function stripHtml(?string $text): string
    {
        $text = trim(strip_tags(html_entity_decode($text ?? '')));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    private function extractSummary($item): string
    {
        foreach ([$item->get_description(), $item->get_content()] as $value) {
            $text = $this->stripHtml((string) ($value ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }
}
