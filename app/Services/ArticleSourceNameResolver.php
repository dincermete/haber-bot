<?php

namespace App\Services;

class ArticleSourceNameResolver
{
    /** @var list<string> */
    private const REJECT_NAMES = [
        'dedi', 'açıkladı', 'ifade', 'etti', 'oldu', 'sonra', 'için', 'gibi', 'olarak',
        'haber', 'haberler', 'gündem', 'sondakika', 'elazığ', 'elazig',
    ];

    public function resolve(
        ?string $sourceName,
        ?string $sourceUrl = null,
        ?string $link = null,
        ?string $title = null,
        ?string $plainSummary = null,
    ): ?string {
        $candidates = [];

        if ($fromDomain = $this->fromUrl($sourceUrl)) {
            $candidates[] = $fromDomain;
        }

        if (! $fromDomain && ($fromLink = $this->fromUrl($link))) {
            $candidates[] = $fromLink;
        }

        if ($fromText = $this->fromText($this->combinedText($title, $plainSummary))) {
            $candidates[] = $fromText;
        }

        if ($normalized = $this->normalizeRssSourceName($sourceName)) {
            $candidates[] = $normalized;
        }

        foreach ($candidates as $candidate) {
            if ($this->isUsablePublisherName($candidate)) {
                return $this->truncate($candidate, 120);
            }
        }

        return null;
    }

    public function stripTrailingPublisher(string $text, ?string $publisherName): string
    {
        $publisherName = trim((string) $publisherName);
        if ($publisherName === '' || trim($text) === '') {
            return $text;
        }

        $quoted = preg_quote($publisherName, '/');
        $text = preg_replace('/[\s\-–—|]+\s*'.$quoted.'\s*$/iu', '', $text) ?? $text;
        $text = preg_replace('/\n\s*'.$quoted.'\s*$/iu', '', $text) ?? $text;

        return trim($text);
    }

    private function fromUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! str_starts_with($url, 'http')) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if (str_contains($host, 'google.') || str_contains($host, 'news.google')) {
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return null;
        }

        $secondLevelTlds = ['com', 'net', 'org', 'gov', 'edu', 'co'];
        $label = $parts[count($parts) - 2];

        if (in_array($label, $secondLevelTlds, true) && count($parts) >= 3) {
            $label = $parts[count($parts) - 3];
        }

        return $this->formatDomainLabel($label);
    }

    private function formatDomainLabel(string $label): string
    {
        $label = preg_replace('/[^a-z0-9\-]/', '', strtolower($label)) ?? $label;
        if ($label === '') {
            return '';
        }

        if (preg_match('/^([a-z]+)(\d+)$/', $label, $matches)) {
            return ucfirst($matches[1]).$matches[2];
        }

        if (str_contains($label, '-')) {
            return collect(explode('-', $label))
                ->filter()
                ->map(fn (string $part) => ucfirst($part))
                ->implode(' ');
        }

        return ucfirst($label);
    }

    private function fromText(string $text): ?string
    {
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));
        if ($text === '') {
            return null;
        }

        if (preg_match('/Kaynak:\s*(.+?)(?:\n|$)/iu', $text, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/[\s\-–—|]\s*([A-ZİĞÜŞÖÇ][A-Za-zİĞÜŞÖÇığüşöç0-9]{2,28})\s*$/u', $text, $matches)) {
            return trim($matches[1]);
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\n/u', $text) ?: [])));
        if ($lines !== []) {
            $last = $lines[array_key_last($lines)];
            if (preg_match('/^[A-ZİĞÜŞÖÇ][A-Za-zİĞÜŞÖÇığüşöç0-9]{2,28}$/u', $last)) {
                return $last;
            }
        }

        return null;
    }

    private function combinedText(?string $title, ?string $plainSummary): string
    {
        return trim(trim((string) $title)."\n".trim((string) $plainSummary));
    }

    private function normalizeRssSourceName(?string $sourceName): ?string
    {
        $sourceName = trim((string) $sourceName);
        if ($sourceName === '') {
            return null;
        }

        $sourceName = trim($sourceName, " \t\n\r\0\x0B\"'");
        $sourceName = preg_replace('/\s*-\s*Google Haberler\s*$/iu', '', $sourceName) ?? $sourceName;
        $sourceName = trim($sourceName);

        return $sourceName !== '' ? $sourceName : null;
    }

    private function isUsablePublisherName(string $name): bool
    {
        $name = trim($name);
        if (mb_strlen($name) < 3) {
            return false;
        }

        if (preg_match('/google\s*haberler|news\.google/i', $name)) {
            return false;
        }

        if (in_array(mb_strtolower($name), self::REJECT_NAMES, true)) {
            return false;
        }

        return true;
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max);
    }
}
