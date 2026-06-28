<?php

namespace App\Services;

use App\Exceptions\TelegramException;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    private const API_BASE = 'https://api.telegram.org/bot';

    public function __construct(
        private readonly ImageCacheService $imageCache,
        private readonly ArticleSourceNameResolver $sourceNameResolver,
    ) {}

    public function isEnabled(): bool
    {
        return Setting::get('telegram_enabled', '0') === '1' && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return $this->botToken() !== '' && $this->chatId() !== '';
    }

    public function notifyNewArticle(Article $article): void
    {
        $message = $this->formatNewArticleMessage($article);

        if ($this->shouldSendPhoto()) {
            $imagePath = $this->resolveArticleImagePath($article);
            if ($imagePath !== null) {
                try {
                    $this->sendPhoto($imagePath, $this->truncateCaption($message));

                    return;
                } catch (TelegramException) {
                    // Metin mesajına düş
                }
            }
        }

        $this->sendMessage($message);
    }

    public function sendTestMessage(): void
    {
        $this->sendMessage(
            "✅ <b>Haber Bot</b> Telegram bağlantısı çalışıyor.\n\n"
            .'Yeni RSS haberleri bu kanala düşecek.'
        );
    }

    public function formatNewArticleMessage(Article $article): string
    {
        $title = htmlspecialchars(trim($article->title ?: 'Başlıksız'), ENT_QUOTES | ENT_HTML5);
        $sourceUrl = trim($article->effective_source_url);
        $editUrl = url(ArticleResource::getUrl('edit', ['record' => $article], panel: 'admin'));

        $sourceName = $this->sourceNameResolver->resolve(
            $article->source_name,
            $article->source_url,
            $article->link,
            $article->title,
            strip_tags((string) $article->summary),
        );

        $lines = [
            '🆕 <b>Yeni Haber</b>',
            '',
            "<b>{$title}</b>",
        ];

        $excerpt = $this->plainExcerpt((string) $article->summary);
        if ($excerpt !== '') {
            $lines[] = '';
            $lines[] = htmlspecialchars($excerpt, ENT_QUOTES | ENT_HTML5);
        }

        if ($sourceName !== null && $sourceName !== '') {
            $lines[] = '';
            $lines[] = 'Kaynak: '.htmlspecialchars($sourceName, ENT_QUOTES | ENT_HTML5);
        }

        $links = [];
        if ($this->isValidHttpUrl($sourceUrl)) {
            $links[] = '<a href="'.htmlspecialchars($sourceUrl, ENT_QUOTES | ENT_HTML5).'">Kaynağa Git</a>';
        }
        $links[] = '<a href="'.htmlspecialchars($editUrl, ENT_QUOTES | ENT_HTML5).'">Düzenle</a>';

        $lines[] = '';
        $lines[] = implode(' · ', $links);

        return implode("\n", $lines);
    }

    private function sendMessage(string $message): void
    {
        $this->request('sendMessage', [
            'chat_id' => $this->chatId(),
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
        ]);
    }

    private function sendPhoto(string $localPath, string $caption): void
    {
        $token = $this->botToken();
        $url = self::API_BASE.$token.'/sendPhoto';

        $response = Http::timeout(30)
            ->attach('photo', file_get_contents($localPath), basename($localPath))
            ->post($url, [
                'chat_id' => $this->chatId(),
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);

        $this->assertOkResponse($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, array $payload): void
    {
        $url = self::API_BASE.$this->botToken().'/'.$method;

        try {
            $response = Http::timeout(20)->post($url, $payload);
        } catch (\Throwable $e) {
            throw new TelegramException('Telegram bağlantı hatası: '.$e->getMessage(), 0, $e);
        }

        $this->assertOkResponse($response);
    }

    private function assertOkResponse(\Illuminate\Http\Client\Response $response): void
    {
        if ($response->status() === 401) {
            throw new TelegramException('Telegram hatası: Geçersiz bot token.');
        }

        $data = $response->json();

        if ($response->status() === 400) {
            throw new TelegramException('Telegram hatası: '.($data['description'] ?? 'Bad Request'));
        }

        if (! $response->successful()) {
            throw new TelegramException('Telegram hatası: HTTP '.$response->status());
        }

        if (! ($data['ok'] ?? false)) {
            throw new TelegramException('Telegram hatası: '.($data['description'] ?? 'Bilinmeyen hata'));
        }
    }

    private function resolveArticleImagePath(Article $article): ?string
    {
        $imageUrl = $article->source_image_url;
        if (! filled($imageUrl)) {
            return null;
        }

        return $this->imageCache->downloadToLocal($imageUrl, $article->effective_source_url);
    }

    private function shouldSendPhoto(): bool
    {
        return Setting::get('telegram_send_photo', '1') === '1';
    }

    private function botToken(): string
    {
        return trim((string) Setting::get('telegram_bot_token', ''));
    }

    private function chatId(): string
    {
        return trim((string) Setting::get('telegram_chat_id', ''));
    }

    private function plainExcerpt(string $html): string
    {
        $text = trim(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5)));
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) > 280) {
            return mb_substr($text, 0, 277).'…';
        }

        return $text;
    }

    private function truncateCaption(string $message): string
    {
        if (mb_strlen($message) <= 1024) {
            return $message;
        }

        return mb_substr($message, 0, 1021).'…';
    }

    private function isValidHttpUrl(string $url): bool
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return false;
        }

        return (bool) parse_url($url, PHP_URL_HOST);
    }
}
