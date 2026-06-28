<?php

namespace App\Services;

use App\Models\Setting;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiService
{
    private const TIMEOUT = 10;

    public function rewriteArticle(string $title, string $summary): array
    {
        return [
            'title' => $this->rewriteTitle($title),
            'summary' => $this->rewriteSummary($title, $summary),
        ];
    }

    public function rewriteTitle(string $title): string
    {
        $title = $this->truncate(strip_tags($title), 200);

        if ($title === '') {
            throw new \InvalidArgumentException('Başlık boş olamaz.');
        }

        $systemPrompt = $this->systemPrompt('ai_prompt_title');

        $content = $this->chatCompletion($systemPrompt, "Başlık: {$title}");

        return $this->parseJsonResponse($content, $title, '')['title'] ?: $title;
    }

    public function rewriteSummary(
        string $title,
        mixed $summary,
        ?string $sourceName = null,
        ?string $sourceUrl = null,
        ?string $link = null,
    ): string {
        $plainSummary = $this->htmlToPlainText($this->richContentToHtml($summary));
        $title = $this->truncate(strip_tags($title), 200);
        $plainSummary = $this->truncate($plainSummary, 800);

        $resolver = app(ArticleSourceNameResolver::class);
        $sourceName = $resolver->resolve($sourceName, $sourceUrl, $link, $title, $plainSummary);
        $plainSummary = $resolver->stripTrailingPublisher($plainSummary, $sourceName);

        $systemPrompt = $this->summaryRewriteSystemPrompt();

        $userPrompt = $this->buildSummaryUserPrompt($title, $plainSummary, $sourceName);

        $content = $this->chatCompletion($systemPrompt, $userPrompt, 0.5);

        $body = $this->parseJsonResponse($content, '', $plainSummary)['summary'] ?: $plainSummary;
        $body = $this->stripAccidentalFooter($body);

        $hashtags = $this->generateHashtags($title, $body);

        return $this->assembleSummaryOutput($body, $sourceName, $hashtags);
    }

    private function summaryRewriteSystemPrompt(): string
    {
        return $this->systemPrompt('ai_prompt_summary')."\n\n"
            .'ÖNEMLİ: JSON summary alanına yalnızca haber gövdesini yaz. Kaynak satırı ve hashtag ekleme; bunları sistem sonradan otomatik ekler.';
    }

    private function stripAccidentalFooter(string $body): string
    {
        $body = preg_replace('/\n\s*Kaynak:\s*.+$/su', '', $body) ?? $body;
        $body = preg_replace('/\n\s*(?:#\S+\s*)+$/su', '', $body) ?? $body;

        return trim($body);
    }

    private function generateHashtags(string $title, string $body): string
    {
        $system = 'Sen bir sosyal medya editörüsün. Verilen haber için Instagram\'da popüler olabilecek tam 5 Türkçe hashtag üret. '
            .'Yanıt JSON: {"hashtags": "#etiket1 #etiket2 #etiket3 #etiket4 #etiket5"} — tek satır string, # ile başlasın.';

        $user = "Başlık: {$title}\n\nÖzet: ".$this->truncate($body, 500);

        try {
            $content = $this->chatCompletion($system, $user, 0.3);
            $decoded = json_decode($content, true);
            if (! is_array($decoded) && preg_match('/\{.*\}/s', $content, $match)) {
                $decoded = json_decode($match[0], true);
            }

            $tags = trim((string) ($decoded['hashtags'] ?? ''));
            if (preg_match_all('/#[\p{L}\p{N}_]+/u', $tags, $matches) && count($matches[0]) >= 3) {
                return implode(' ', array_slice($matches[0], 0, 5));
            }
        } catch (\Throwable) {
            // fallback below
        }

        return '#gündem #haber #türkiye #elazığ #sondakika';
    }

    private function assembleSummaryOutput(string $body, ?string $sourceName, string $hashtags): string
    {
        $parts = [trim($body)];

        if ($sourceName !== null && $sourceName !== '') {
            $parts[] = 'Kaynak: '.$sourceName;
        }

        $hashtags = trim($hashtags);
        if ($hashtags !== '') {
            $parts[] = $hashtags;
        }

        return $this->plainTextToHtml(implode("\n\n", array_filter($parts)));
    }

    private function buildSummaryUserPrompt(string $title, string $plainSummary, ?string $sourceName): string
    {
        $parts = [];

        if ($title !== '') {
            $parts[] = "Başlık: {$title}";
        }

        if ($sourceName !== null && $sourceName !== '') {
            $parts[] = "Kaynak adı: {$sourceName}";
        }

        if ($plainSummary !== '') {
            $parts[] = "Özet: {$plainSummary}";
        }

        return implode("\n\n", $parts) ?: 'Özet: Başlıksız';
    }

    public function richContentToHtml(mixed $content): string
    {
        if (is_array($content)) {
            return RichContentRenderer::make($content)->toHtml();
        }

        return (string) $content;
    }

    public function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    public function plainTextToHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (str_contains($text, '<p>') || str_contains($text, '<ul>') || str_contains($text, '<ol>')) {
            return $text;
        }

        $paragraphs = preg_split('/\n\s*\n/u', $text) ?: [$text];

        return implode('', array_map(
            fn (string $p) => '<p>'.e(trim($p)).'</p>',
            array_filter(array_map('trim', $paragraphs)),
        ));
    }

    public function generateFromPrompt(string $input, string $mode): array
    {
        $input = trim($input);
        if ($input === '') {
            throw new \InvalidArgumentException('Girdi boş olamaz.');
        }

        $systemPrompt = $this->systemPrompt('ai_prompt_generate');

        $userPrompt = match ($mode) {
            'url' => $this->buildUrlPrompt($input),
            'keyword' => "Anahtar kelime: {$input}\n\nBu konuda kısa bir haber başlığı ve özeti yaz.",
            default => throw new \InvalidArgumentException('Geçersiz mod: '.$mode),
        };

        $content = $this->chatCompletion($systemPrompt, $userPrompt);

        return $this->parseJsonResponse($content, '', '');
    }

    private function buildUrlPrompt(string $url): string
    {
        $meta = $this->fetchPageMeta($url);

        if ($meta['title'] === '' && $meta['description'] === '') {
            return "URL: {$url}\n\nBu URL'den haber başlığı ve özeti üret.";
        }

        return "URL: {$url}\nSayfa başlığı: {$meta['title']}\nMeta açıklama: {$meta['description']}\n\nBu bilgilerden haber başlığı ve özeti yaz.";
    }

    private function fetchPageMeta(string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => 'HaberBot/1.0'])
                ->get($url);

            if (! $response->successful()) {
                return ['title' => '', 'description' => ''];
            }

            $html = $response->body();
            $title = '';
            $description = '';

            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
                $title = trim(html_entity_decode($m[1]));
            }
            if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
                $description = trim(html_entity_decode($m[1]));
            } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\']/i', $html, $m)) {
                $description = trim(html_entity_decode($m[1]));
            }

            return [
                'title' => $this->truncate($title, 200),
                'description' => $this->truncate($description, 500),
            ];
        } catch (\Throwable) {
            return ['title' => '', 'description' => ''];
        }
    }

    private function systemPrompt(string $key): string
    {
        $defaults = Setting::aiPromptDefaults();

        return (string) Setting::get($key, $defaults[$key] ?? '');
    }

    private function chatCompletion(string $systemPrompt, string $userPrompt, float $temperature = 0.7): string
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new \RuntimeException('OpenAI API anahtarı yapılandırılmamış.');
        }

        $model = (string) Setting::get('ai_openai_model', env('OPENAI_MODEL', 'gpt-4o-mini'));

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => $temperature,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('OpenAI hatası: '.$response->json('error.message', $response->body()));
            }

            return (string) $response->json('choices.0.message.content', '');
        } catch (\Illuminate\Http\Client\ConnectionException) {
            throw new \RuntimeException('OpenAI sunucularına bağlanılamadı (Zaman aşımı). Lütfen tekrar deneyin.');
        } catch (\Throwable $e) {
            throw new \RuntimeException('Yapay zeka servisinde bir hata oluştu: ' . $e->getMessage());
        }
    }

    private function parseJsonResponse(string $content, string $fallbackTitle, string $fallbackSummary): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $content, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('AI yanıtı işlenemedi.');
        }

        return [
            'title' => trim($this->stringifyValue($decoded['title'] ?? $fallbackTitle)),
            'summary' => trim($this->stringifyValue($decoded['summary'] ?? $fallbackSummary)),
        ];
    }

    private function apiKey(): string
    {
        $fromSettings = (string) Setting::get('ai_openai_api_key', '');

        return $fromSettings !== '' ? $fromSettings : (string) env('OPENAI_API_KEY', '');
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            return $this->htmlToPlainText($this->richContentToHtml($value));
        }

        return (string) $value;
    }

    private function truncate(string $text, int $maxChars): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars).'…';
    }
}
