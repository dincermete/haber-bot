<?php

namespace App\Services;

use App\Exceptions\GoldPriceFetchException;
use Illuminate\Support\Facades\Http;

class EkeoGoldPriceService
{
    private const string URL = 'https://fiyat.ekeo.org.tr/';

  /** @var list<string> */
    private const array EXPECTED_LABELS = [
        '24 AYAR HAS',
        '22 AYAR',
        '14 AYAR',
        'BEŞLİ',
        'ATA LİRA',
        'YARIM',
        'ÇEYREK',
        '24 AYAR 1 GRAM',
    ];

    /**
     * @return array{
     *     items: list<array{label: string, purchase: string, sale: string}>,
     *     source_updated_at: ?string,
     *     fetched_at: string
     * }
     */
    public function fetch(): array
    {
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'HaberBot/1.0'])
            ->get(self::URL);

        if (! $response->successful()) {
            throw new GoldPriceFetchException('EKEO sitesine erişilemedi: HTTP '.$response->status());
        }

        return $this->parseHtml($response->body());
    }

    /**
     * @return array{
     *     items: list<array{label: string, purchase: string, sale: string}>,
     *     source_updated_at: ?string,
     *     fetched_at: string
     * }
     */
    private function parseHtml(string $html): array
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query("//li[contains(@class, 'veri')]");

        if ($rows === false || $rows->length === 0) {
            throw new GoldPriceFetchException('EKEO fiyat tablosu bulunamadı.');
        }

        $items = [];

        foreach ($rows as $row) {
            $labelNode = $xpath->query(".//li[contains(@class, 'v_a')]", $row)->item(0);
            $purchaseNode = $xpath->query(".//li[contains(@class, 'v_d1x')]", $row)->item(0);
            $saleNode = $xpath->query(".//li[contains(@class, 'v_d2x')]", $row)->item(0);

            if (! $labelNode || ! $purchaseNode || ! $saleNode) {
                continue;
            }

            $label = $this->normalizeText($labelNode->textContent);
            $purchase = $this->extractPriceFromCell($purchaseNode);
            $sale = $this->extractPriceFromCell($saleNode);

            if ($label === '' || $sale === '') {
                continue;
            }

            if ($purchase === '' && str_contains(mb_strtoupper($label), '14 AYAR')) {
                $purchase = 'DAHİL';
            }

            if ($purchase === '') {
                continue;
            }

            $items[] = [
                'label' => $label,
                'purchase' => $purchase,
                'sale' => $sale,
            ];
        }

        if (count($items) < count(self::EXPECTED_LABELS)) {
            throw new GoldPriceFetchException(
                'EKEO fiyat verisi eksik: '.count($items).'/'.count(self::EXPECTED_LABELS).' kalem.'
            );
        }

        $sourceUpdatedAt = null;
        if (preg_match('/SON GÜNCELLEME:\s*<span[^>]*>\s*([\d:]+)\s*<\/span>/iu', $html, $m)) {
            $sourceUpdatedAt = trim($m[1]);
        }

        return [
            'items' => $items,
            'source_updated_at' => $sourceUpdatedAt,
            'fetched_at' => now()->timezone('Europe/Istanbul')->toIso8601String(),
        ];
    }

    private function extractPriceFromCell(\DOMNode $cell): string
    {
        $xpath = new \DOMXPath($cell->ownerDocument);
        $priceDivs = $xpath->query(".//div[not(contains(@class, 'purc'))]", $cell);

        if ($priceDivs === false) {
            return '';
        }

        foreach ($priceDivs as $div) {
            $text = $this->normalizeText($div->textContent);
            if ($text === '' || str_contains(mb_strtoupper($text), 'İŞÇİLİK')) {
                continue;
            }

            if (preg_match('/[\d.,]+/', $text)) {
                return $text;
            }
        }

        return '';
    }

    private function normalizeText(?string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) $text)) ?? '';

        return $text;
    }

    public function buildTitle(array $payload): string
    {
        $date = now()->timezone('Europe/Istanbul')->format('d.m.Y');
        $has = collect($payload['items'] ?? [])->firstWhere('label', '24 AYAR HAS');
        $hasSale = $has['sale'] ?? '—';

        return "Elazığ Altın Fiyatları — {$date} (Has: {$hasSale} TL)";
    }

    public function buildSummaryHtml(array $payload, bool $stale = false, ?string $staleNote = null): string
    {
        $lines = ['<p><strong>Elazığ Kuyumcular Odası</strong> güncel altın fiyatları:</p>', '<table border="1" cellpadding="6"><thead><tr><th>Ürün</th><th>Alış</th><th>Satış</th></tr></thead><tbody>'];

        foreach ($payload['items'] ?? [] as $item) {
            $lines[] = sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                e($item['label']),
                e($item['purchase']),
                e($item['sale']),
            );
        }

        $lines[] = '</tbody></table>';

        if ($stale && $staleNote) {
            $lines[] = '<p><em>'.e($staleNote).'</em></p>';
        } elseif (filled($payload['source_updated_at'] ?? null)) {
            $lines[] = '<p><small>Kaynak güncelleme: '.e($payload['source_updated_at']).'</small></p>';
        }

        return implode("\n", $lines);
    }
}
