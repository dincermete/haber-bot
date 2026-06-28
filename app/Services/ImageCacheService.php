<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageCacheService
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function downloadToLocal(string $url, ?string $referer = null): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if ($this->isLocalPublicUrl($url)) {
            return $this->resolveLocalPublicPath($url);
        }

        if (! str_starts_with($url, 'http')) {
            return null;
        }

        $hash = md5($url);
        $extension = $this->guessExtension($url);
        $relativePath = "tmp/articles/{$hash}.{$extension}";
        $fullPath = storage_path('app/'.$relativePath);

        if (is_file($fullPath) && filesize($fullPath) > 100) {
            return $fullPath;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => self::BROWSER_UA,
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer' => $referer ?? $this->refererFromUrl($url),
                ])
                ->get($url);

            if (! $response->successful() || strlen($response->body()) < 100) {
                return null;
            }

            Storage::disk('local')->makeDirectory('tmp/articles');
            $dir = dirname($fullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($fullPath, $response->body());

            return $fullPath;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isAccessible(string $url, ?string $referer = null): bool
    {
        return $this->downloadToLocal($url, $referer) !== null;
    }

    private function isLocalPublicUrl(string $url): bool
    {
        $publicBase = rtrim(asset('storage'), '/');

        return str_starts_with($url, $publicBase.'/') || str_starts_with($url, '/storage/');
    }

    private function resolveLocalPublicPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        $relative = Str::after($path, '/storage/');
        $fullPath = Storage::disk('public')->path($relative);

        return is_file($fullPath) ? $fullPath : null;
    }

    private function guessExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true) ? $ext : 'jpg';
    }

    private function refererFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (is_array($parts) && ! empty($parts['scheme']) && ! empty($parts['host'])) {
            return $parts['scheme'].'://'.$parts['host'].'/';
        }

        return '';
    }
}
