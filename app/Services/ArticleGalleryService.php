<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleGalleryService
{
    public function __construct(
        private readonly ArticleImageResolver $imageResolver,
    ) {}

    public function selectImage(Article $article, string $url): void
    {
        $url = trim($url);
        if ($url === '') {
            return;
        }

        $article->update(['selected_image_url' => $url]);
    }

    /**
     * Galeri listesini UI için sadeleştirir: tekrarları, ikonları ve düşük kalite URL'leri ayıklar.
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    public function normalizeForDisplay(array $urls): array
    {
        $seen = [];
        $result = [];

        foreach ($urls as $url) {
            $url = $this->toDisplayUrl(trim($url));
            if ($url === '') {
                continue;
            }

            if (str_starts_with(strtolower($url), 'data:')) {
                continue;
            }

            if (str_contains(strtolower($url), 'data:image/')) {
                continue;
            }

            if ($this->imageResolver->isLowQualityImageUrl($url)) {
                continue;
            }

            $lower = strtolower($url);
            if (preg_match('/logo|icon|sprite|avatar|badge|emoji|1x1|pixel|spacer|placeholder/i', $lower)) {
                continue;
            }

            if (preg_match('/\.(svg|gif)(\?|$)/', $lower)) {
                continue;
            }

            $key = $this->fingerprint($url);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $url;
        }

        return array_slice($result, 0, 20);
    }

    public function storeUpload(Article $article, string $tempPath): string
    {
        $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = Str::uuid().'.'.$extension;
        $relativePath = "articles/uploads/{$article->id}/{$filename}";

        Storage::disk('public')->makeDirectory("articles/uploads/{$article->id}");
        Storage::disk('public')->put($relativePath, file_get_contents($tempPath));

        $url = Storage::disk('public')->url($relativePath);
        $article->update(['selected_image_url' => $url]);

        return $url;
    }

    public function toDisplayUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        if (! str_starts_with($url, 'http')) {
            return '';
        }

        return $url;
    }

    private function fingerprint(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        $path = preg_replace('/[-_]?\d{2,4}x\d{2,4}/', '', $path) ?? $path;
        $path = preg_replace('/\/(w|h|width|height)=\d+/i', '', $path) ?? $path;

        return $host.$path;
    }
}