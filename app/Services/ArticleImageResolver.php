<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ArticleImageResolver
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function __construct(
        private readonly GoogleNewsUrlDecoder $googleNewsUrlDecoder,
    ) {}

    public function resolve(?string $imageUrl, ?string $articleLink): ?string
    {
        $articleLink = trim((string) $articleLink);
        $pageUrl = $this->resolveArticlePageUrl($articleLink);

        $imageUrl = trim((string) $imageUrl);
        if ($imageUrl !== '' && $this->isLowQualityImageUrl($imageUrl)) {
            $imageUrl = '';
        }

        $imageUrl = $this->normalizeUrl($imageUrl, $pageUrl ?: $articleLink);

        if ($imageUrl && $this->urlLooksLikeImage($imageUrl)) {
            return $imageUrl;
        }

        if ($imageUrl && $this->probeImageUrl($imageUrl)) {
            return $imageUrl;
        }

        if (! $pageUrl) {
            return $imageUrl ?: null;
        }

        return $this->scrapeFromArticlePage($pageUrl, $articleLink) ?? $imageUrl;
    }

    private function resolveArticlePageUrl(string $articleLink): ?string
    {
        if ($articleLink === '') {
            return null;
        }

        if ($this->googleNewsUrlDecoder->isGoogleNewsUrl($articleLink)) {
            return $this->googleNewsUrlDecoder->resolve($articleLink) ?? $articleLink;
        }

        return $articleLink;
    }

    public function normalizeUrl(string $url, ?string $baseUrl = null): ?string
    {
        if ($url === '') {
            return null;
        }

        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (! $baseUrl) {
            return null;
        }

        $parts = parse_url($baseUrl);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }

        $path = $parts['path'] ?? '/';
        $dir = Str::beforeLast($path, '/');

        return $origin.($dir !== '' ? $dir : '').'/'.$url;
    }

    private function urlLooksLikeImage(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|avif|bmp)(\?|$)/i', $path);
    }

    private function probeImageUrl(string $url): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => self::BROWSER_UA, 'Accept' => 'image/*,*/*'])
                ->head($url);

            if ($response->successful()) {
                $type = strtolower((string) $response->header('Content-Type'));

                return $type === '' || str_starts_with($type, 'image/');
            }
        } catch (\Throwable) {
            // try GET below
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders(['User-Agent' => self::BROWSER_UA, 'Accept' => 'image/*,*/*'])
                ->get($url);

            return $response->successful() && str_starts_with(strtolower((string) $response->header('Content-Type', 'image/')), 'image/');
        } catch (\Throwable) {
            return false;
        }
    }

    private function scrapeFromArticlePage(string $articleLink, string $originalLink = ''): ?string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => self::BROWSER_UA,
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'tr-TR,tr;q=0.9,en;q=0.8',
                ])
                ->get($articleLink);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();
            $base = (string) $response->effectiveUri();
            $articleId = $this->extractArticleIdFromUrl($originalLink ?: $articleLink);

            return $this->pickBestImageCandidate($html, $base, $articleId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function pickBestImageCandidate(string $html, string $baseUrl, ?string $articleId): ?string
    {
        $candidates = [];

        foreach ($this->metaImageCandidates($html) as $candidate) {
            $candidates[] = ['url' => $candidate, 'score' => 50];
        }

        foreach ($this->jsonLdImageCandidates($html) as $candidate) {
            $candidates[] = ['url' => $candidate, 'score' => 60];
        }

        if (preg_match_all('#https?://[^"\'\s<>]+\.(?:jpe?g|webp|png)(?:\?[^"\'\s<>]*)?#i', $html, $matches)) {
            foreach ($matches[0] as $candidate) {
                $candidates[] = ['url' => $candidate, 'score' => 20];
            }
        }

        if (preg_match_all('/<img[^>]+(?:data-src|data-lazy-src|src)=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $candidate) {
                $candidates[] = ['url' => $candidate, 'score' => 10];
            }
        }

        $best = null;
        $bestScore = PHP_INT_MIN;

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeUrl(trim($candidate['url']), $baseUrl);
            if (! $normalized || $this->isBadImageCandidate($normalized)) {
                continue;
            }

            $score = $candidate['score'] + $this->scoreImageCandidate($normalized, $articleId);
            if ($score > $bestScore && $this->probeImageUrl($normalized)) {
                $bestScore = $score;
                $best = $normalized;
            }
        }

        return $best;
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

        if (isset($data['image'])) {
            $images = array_merge($images, $this->extractImagesFromJsonLd($data['image']));
        }

        if (isset($data['thumbnailUrl'])) {
            $images = array_merge($images, $this->extractImagesFromJsonLd($data['thumbnailUrl']));
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $images = array_merge($images, $this->extractImagesFromJsonLd($value));
            }
        }

        return $images;
    }

    private function extractArticleIdFromUrl(string $url): ?string
    {
        if (preg_match('/(\d{6,})-haberi/', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('/_(\d{6,})[_\.]/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function scoreImageCandidate(string $url, ?string $articleId): int
    {
        $score = 0;
        $lower = strtolower($url);

        if (preg_match('/\.(jpe?g|webp|png)(\?|$)/', $lower)) {
            $score += 10;
        }

        if (preg_match('/\d{3,4}x\d{3,4}/', $url)) {
            $score += 25;
        }

        if (preg_match('/(foto\.|images?\.|upload|haber|media|cdn|i\.)/i', $url)) {
            $score += 20;
        }

        if (preg_match('/1280|1920|1200|1080|720/', $url)) {
            $score += 15;
        }

        if ($articleId && str_contains($url, $articleId)) {
            $score += 40;
        }

        if (preg_match('/crop\/\d+x\d+\//', $lower)) {
            $score -= 10;
        }

        return $score;
    }

    private function isBadImageCandidate(string $url): bool
    {
        if ($this->isGenericPlaceholder($url)) {
            return true;
        }

        $lower = strtolower($url);

        if (preg_match('/\.(svg|gif)(\?|$)/', $lower)) {
            return true;
        }

        foreach (['logo', 'icon', 'avatar', 'headericons', 'placeholder', 'spacer', 'pixel', 'badge', 'emoji'] as $bad) {
            if (str_contains($lower, $bad)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function metaImageCandidates(string $html): array
    {
        $patterns = [
            '/<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::secure_url)?["\']/i',
            '/<meta[^>]+name=["\']twitter:image(?::src)?["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image(?::src)?["\']/i',
            '/<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)["\']/i',
        ];

        $found = [];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $found[] = trim($matches[1]);
            }
        }

        return array_values(array_unique($found));
    }

    private function isGenericPlaceholder(string $url): bool
    {
        if (str_contains($url, 'googleusercontent.com/J6_coFbogxhRI')) {
            return true;
        }

        return (bool) preg_match('/googleusercontent\.com.*=s0-w\d+-rw/i', $url);
    }

    public function isLowQualityImageUrl(string $url): bool
    {
        return $this->isBadImageCandidate($url) || $this->isGenericPlaceholder($url);
    }
}
