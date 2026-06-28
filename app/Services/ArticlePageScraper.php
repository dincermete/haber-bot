<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ArticlePageScraper
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

    public function __construct(
        private readonly ArticleImageResolver $imageResolver,
    ) {}

    /**
     * @return array{description: string, images: list<string>, title: string}
     */
    public function scrape(string $url): array
    {
        $url = trim($url);

        if ($url === '' || ! str_starts_with($url, 'http')) {
            return $this->emptyResult();
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => self::BROWSER_UA,
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'tr-TR,tr;q=0.9,en;q=0.8',
                ])
                ->get($url);

            if (! $response->successful()) {
                return $this->emptyResult();
            }

            $html = $response->body();
            $baseUrl = (string) $response->effectiveUri();

            return [
                'description' => $this->extractDescription($html),
                'images' => $this->extractGalleryImages($html, $baseUrl),
                'title' => $this->extractTitle($html),
            ];
        } catch (\Throwable) {
            return $this->emptyResult();
        }
    }

    public function needsEnrichment(string $summary, array $galleryImages, ?string $imageUrl, ?string $pageUrl): bool
    {
        if (! $pageUrl || ! str_starts_with($pageUrl, 'http')) {
            return false;
        }

        if (mb_strlen(trim($summary)) < 80) {
            return true;
        }

        if ($galleryImages === []) {
            return true;
        }

        if (! $imageUrl || $this->imageResolver->isLowQualityImageUrl($imageUrl)) {
            return true;
        }

        return false;
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return $this->cleanText($m[1]);
        }

        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            return $this->cleanText($m[1]);
        }

        return '';
    }

    private function extractDescription(string $html): string
    {
        $candidates = [];

        foreach ([
            '/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:description["\']/i',
            '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\']/i',
            '/<meta[^>]+name=["\']twitter:description["\'][^>]+content=["\']([^"\']+)["\']/i',
        ] as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $candidates[] = $this->cleanText($m[1]);
            }
        }

        foreach ($this->jsonLdDescriptions($html) as $desc) {
            $candidates[] = $desc;
        }

        $paragraphs = $this->extractArticleParagraphs($html);
        if ($paragraphs !== '') {
            $candidates[] = $paragraphs;
        }

        foreach ($candidates as $candidate) {
            if (mb_strlen($candidate) >= 40) {
                return $this->truncate($candidate, 1200);
            }
        }

        return $this->truncate($candidates[0] ?? '', 1200);
    }

    /** @return list<string> */
    private function extractGalleryImages(string $html, string $baseUrl): array
    {
        $candidates = [];
        $scored = [];

        foreach ($this->metaImageCandidates($html) as $url) {
            $scored[] = ['url' => $url, 'score' => 50];
        }

        foreach ($this->jsonLdImageCandidates($html) as $url) {
            $scored[] = ['url' => $url, 'score' => 60];
        }

        if (preg_match_all('/<img[^>]+(?:data-src|data-lazy-src|data-original|srcset|src)=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $raw) {
                $url = trim(explode(' ', $raw)[0]);
                if ($url !== '') {
                    $scored[] = ['url' => $url, 'score' => 15];
                }
            }
        }

        if (preg_match_all('#https?://[^"\'\s<>]+\.(?:jpe?g|webp|png)(?:\?[^"\'\s<>]*)?#i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $scored[] = ['url' => $url, 'score' => 10];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        foreach ($scored as $item) {
            $normalized = $this->imageResolver->normalizeUrl(trim($item['url']), $baseUrl);
            if (! $normalized || $this->isBadGalleryImage($normalized)) {
                continue;
            }
            if (! in_array($normalized, $candidates, true)) {
                $candidates[] = $normalized;
            }
            if (count($candidates) >= 12) {
                break;
            }
        }

        return $candidates;
    }

    private function extractArticleParagraphs(string $html): string
    {
        $chunks = [];

        if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $articleMatch)) {
            $html = $articleMatch[1];
        }

        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            foreach ($matches[1] as $p) {
                $text = $this->cleanText($p);
                if (mb_strlen($text) >= 40) {
                    $chunks[] = $text;
                }
                if (count($chunks) >= 3) {
                    break;
                }
            }
        }

        return implode(' ', $chunks);
    }

    /** @return list<string> */
    private function jsonLdDescriptions(string $html): array
    {
        $found = [];

        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $blocks)) {
            return $found;
        }

        foreach ($blocks[1] as $json) {
            $data = json_decode(html_entity_decode(trim($json)), true);
            if (! is_array($data)) {
                continue;
            }

            foreach ($this->collectJsonLdDescriptions($data) as $desc) {
                $found[] = $desc;
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function collectJsonLdDescriptions(mixed $data): array
    {
        $descriptions = [];

        if (is_array($data)) {
            if (isset($data['description']) && is_string($data['description'])) {
                $descriptions[] = $this->cleanText($data['description']);
            }

            foreach ($data as $value) {
                if (is_array($value)) {
                    $descriptions = array_merge($descriptions, $this->collectJsonLdDescriptions($value));
                }
            }
        }

        return $descriptions;
    }

    /** @return list<string> */
    private function jsonLdImageCandidates(string $html): array
    {
        $found = [];

        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $blocks)) {
            return $found;
        }

        foreach ($blocks[1] as $json) {
            $data = json_decode(html_entity_decode(trim($json)), true);
            if (! is_array($data)) {
                continue;
            }

            foreach ($this->extractImagesFromJsonLd($data) as $url) {
                $found[] = $url;
            }
        }

        return array_values(array_unique($found));
    }

    /** @return list<string> */
    private function extractImagesFromJsonLd(mixed $data): array
    {
        $images = [];

        if (is_string($data) && str_starts_with($data, 'http')) {
            return [$data];
        }

        if (! is_array($data)) {
            return $images;
        }

        foreach (['image', 'thumbnailUrl'] as $key) {
            if (isset($data[$key])) {
                $images = array_merge($images, $this->extractImagesFromJsonLd($data[$key]));
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $images = array_merge($images, $this->extractImagesFromJsonLd($value));
            }
        }

        return $images;
    }

    /** @return list<string> */
    private function metaImageCandidates(string $html): array
    {
        $patterns = [
            '/<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::secure_url)?["\']/i',
            '/<meta[^>]+name=["\']twitter:image(?::src)?["\'][^>]+content=["\']([^"\']+)["\']/i',
        ];

        $found = [];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $found[] = trim($matches[1]);
            }
        }

        return array_values(array_unique($found));
    }

    private function isBadGalleryImage(string $url): bool
    {
        if ($this->imageResolver->isLowQualityImageUrl($url)) {
            return true;
        }

        $lower = strtolower($url);

        foreach (['logo', 'icon', 'avatar', 'sprite', 'emoji', '1x1', 'tracking', 'pixel'] as $bad) {
            if (str_contains($lower, $bad)) {
                return true;
            }
        }

        return (bool) preg_match('/\.(svg|gif)(\?|$)/', $lower);
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    private function truncate(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max).'…';
    }

    /** @return array{description: string, images: list<string>, title: string} */
    private function emptyResult(): array
    {
        return ['description' => '', 'images' => [], 'title' => ''];
    }
}
