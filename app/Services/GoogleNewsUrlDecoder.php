<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleNewsUrlDecoder
{
    private const BATCH_EXECUTE_URL = 'https://news.google.com/_/DotsSplashUi/data/batchexecute';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

    public function isGoogleNewsUrl(string $url): bool
    {
        return str_contains($url, 'news.google.com') && str_contains($url, '/articles/');
    }

    public function resolve(string $googleNewsUrl): ?string
    {
        if (! $this->isGoogleNewsUrl($googleNewsUrl)) {
            return $googleNewsUrl;
        }

        if ($legacy = $this->decodeLegacyEmbeddedUrl($googleNewsUrl)) {
            return $legacy;
        }

        return $this->decodeViaBatchExecute($googleNewsUrl);
    }

    private function decodeLegacyEmbeddedUrl(string $url): ?string
    {
        $articleId = $this->extractArticleId($url);

        if (! str_starts_with($articleId, 'CBMi')) {
            return null;
        }

        $padded = $articleId.str_repeat('=', (4 - strlen($articleId) % 4) % 4);
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        if (preg_match('#https?://[^\x00-\x1f\x7f"\'<>\\s]+#', $decoded, $matches)) {
            return rtrim($matches[0], '\\');
        }

        return null;
    }

    private function decodeViaBatchExecute(string $url): ?string
    {
        try {
            $articleId = $this->extractArticleId($url);
            [$signature, $timestamp] = $this->fetchDecodingParams($articleId);

            if (! $signature || ! $timestamp) {
                return null;
            }

            $inner = '["garturlreq",[["X","X",["X","X"],null,null,1,1,"TR:tr",null,1,null,null,null,null,null,0,1],"X","X",1,[1,1,1],1,1,null,0,0,null,0],"'
                .$articleId.'",'.$timestamp.',"'.$signature.'"]';

            $response = Http::timeout(20)
                ->withBody('f.req='.urlencode(json_encode([[['Fbv4je', $inner]]])), 'application/x-www-form-urlencoded;charset=UTF-8')
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Referer' => 'https://news.google.com/',
                ])
                ->post(self::BATCH_EXECUTE_URL);

            if (! $response->successful()) {
                return null;
            }

            return $this->parseBatchExecuteResponse($response->body());
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0: ?string, 1: ?int} */
    private function fetchDecodingParams(string $articleId): array
    {
        foreach ([
            "https://news.google.com/articles/{$articleId}",
            "https://news.google.com/rss/articles/{$articleId}",
        ] as $pageUrl) {
            $page = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept-Language' => 'tr-TR,tr;q=0.9',
                ])
                ->get($pageUrl);

            if (! $page->successful()) {
                continue;
            }

            if (preg_match('/data-n-a-sg="([^"]+)"/', $page->body(), $sigMatch)
                && preg_match('/data-n-a-ts="([^"]+)"/', $page->body(), $tsMatch)) {
                return [$sigMatch[1], (int) $tsMatch[1]];
            }
        }

        return [null, null];
    }

    private function parseBatchExecuteResponse(string $body): ?string
    {
        $parts = preg_split("/\r?\n\r?\n/", $body, 2);

        if (count($parts) === 2) {
            $body = $parts[1];
        }

        if (str_starts_with($body, ")]}'")) {
            $body = ltrim(substr($body, 4));
        }

        $envelopes = json_decode($body, true);

        if (! is_array($envelopes)) {
            if (preg_match('/\["garturlres","([^"]+)"/', $body, $match)) {
                return $match[1];
            }

            return null;
        }

        array_splice($envelopes, -2);

        if (isset($envelopes[0][2])) {
            $payload = json_decode($envelopes[0][2], true);
            if (is_array($payload) && ($payload[0] ?? null) === 'garturlres' && ! empty($payload[1])) {
                return (string) $payload[1];
            }
        }

        foreach ($envelopes as $envelope) {
            if (! is_array($envelope) || count($envelope) < 3) {
                continue;
            }

            $payload = json_decode($envelope[2], true);
            if (is_array($payload) && ($payload[0] ?? null) === 'garturlres' && ! empty($payload[1])) {
                return (string) $payload[1];
            }
        }

        return null;
    }

    private function extractArticleId(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return explode('?', basename($path))[0];
    }
}
