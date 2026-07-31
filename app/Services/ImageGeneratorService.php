<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ImageTemplate;
use App\Enums\ArticleType;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\FontProcessor;
use Intervention\Image\Geometry\Point;
use Intervention\Image\Interfaces\FontInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Typography\FontFactory;

class ImageGeneratorService
{
    public function __construct(
        private readonly ArticleImageResolver $imageResolver,
        private readonly TemplateService $templateService,
        private readonly ImageCacheService $imageCache,
    ) {}

    public function generate(Article $article, ?int $templateId = null): string
    {
        $template = $this->templateService->resolveForArticle($article, $templateId);

        if (! $template) {
            throw new \RuntimeException('Görsel şablonu bulunamadı.');
        }

        $settings = $this->templateService->getSettings($template);
        $canvasW = (int) ($template->canvas_width ?: 1080);
        $canvasH = (int) ($template->canvas_height ?: 1080);

        $imageUrl = $article->effective_image_url;
        if ($imageUrl && $this->imageResolver->isLowQualityImageUrl($imageUrl)) {
            $imageUrl = null;
        }

        if (! $imageUrl) {
            $imageUrl = $this->imageResolver->resolve(null, $article->effective_source_url);
        }

        $referer = $article->effective_source_url;

        $canvas = $this->buildBackgroundLayer($imageUrl, $referer, $canvasW, $canvasH, $settings, $article);
        $canvas = $this->overlayDesignFrame($canvas, $template, $canvasW, $canvasH);

        if ($article->type === ArticleType::Gold) {
            $this->drawGoldSlots($canvas, $article, $settings, $canvasW, $canvasH);
        } elseif ($article->type === ArticleType::Weather) {
            $this->drawWeatherSlots($canvas, $article, $settings, $canvasW, $canvasH);
        } else {
            $this->drawBoundedTitle($canvas, $article->title ?: 'Başlıksız', $settings, $canvasW, $canvasH);
        }

        $relativePath = 'articles/generated/'.$article->id.'.png';
        Storage::disk('public')->makeDirectory('articles/generated');
        $canvas->save(Storage::disk('public')->path($relativePath));

        return $relativePath;
    }

    private function buildBackgroundLayer(?string $imageUrl, ?string $referer, int $width, int $height, array $settings, Article $article): ImageInterface
    {
        if ($article->type?->isSpecial()) {
            $imageUrl = null;
        }

        $imageUrl = $this->imageResolver->normalizeUrl(trim((string) $imageUrl), $referer);

        if ($imageUrl) {
            $localPath = $this->imageCache->downloadToLocal($imageUrl, $referer);

            if ($localPath && is_file($localPath)) {
                try {
                    return Image::decode(file_get_contents($localPath))->cover($width, $height);
                } catch (\Throwable) {
                    // fallback below
                }
            }
        }

        $color = $this->parseRgb((string) ($settings['default_bg_color'] ?? '30,30,40'), [30, 30, 40]);
        $hex = sprintf('#%02x%02x%02x', $color[0], $color[1], $color[2]);

        return Image::createImage($width, $height)->fill($hex);
    }

    private function overlayDesignFrame(ImageInterface $base, ImageTemplate $template, int $width, int $height): ImageInterface
    {
        $path = Storage::disk('public')->path($template->file_path);

        if (! is_file($path)) {
            throw new \RuntimeException("Şablon PNG dosyası bulunamadı: {$template->file_path}");
        }

        $overlay = Image::decode(file_get_contents($path));

        if ($overlay->width() !== $width || $overlay->height() !== $height) {
            $overlay = $overlay->resize($width, $height);
        }

        return $base->insert($overlay, 0, 0);
    }

    private function drawBoundedTitle(ImageInterface $image, string $title, array $settings, int $canvasW, int $canvasH): void
    {
        $padding = (int) ($settings['padding'] ?? 60);
        $fontSize = (int) ($settings['font_size'] ?? 48);
        $wrapWidth = (int) ($settings['wrap_width'] ?? 40);
        $color = $this->parseRgb((string) ($settings['title_color'] ?? '255,255,255'), [255, 255, 255]);
        $hexColor = sprintf('#%02x%02x%02x', $color[0], $color[1], $color[2]);

        $left = $this->clampAxis((int) ($settings['text_x'] ?? 60), $padding, $canvasW - $padding);
        $top = $this->clampAxis((int) ($settings['text_y'] ?? 720), $padding, $canvasH - $padding);
        $bottom = $canvasH - $padding;
        $maxLineWidth = $this->resolveMaxLineWidth($canvasW, $padding, $left, $wrapWidth, $fontSize);
        $lineHeight = $fontSize + 10;

        $fontPath = storage_path('app/fonts/Urbanist-Black.ttf');
        if (! is_file($fontPath)) {
            $fontPath = $this->fallbackFont();
        }

        $lines = $this->buildWrappedLines(trim($title) ?: 'Başlıksız', $fontPath, $fontSize, $hexColor, $maxLineWidth);

        $y = $top;
        foreach ($lines as $line) {
            if ($y + $fontSize > $bottom) {
                break;
            }

            $image->text($line, $left, $y, function (FontFactory $font) use ($fontPath, $fontSize, $hexColor) {
                $font->filename($fontPath);
                $font->size($fontSize);
                $font->color($hexColor);
                $font->align('left', 'top');
            });

            $y += $lineHeight;
        }
    }

    private function clampAxis(int $value, int $min, int $max): int
    {
        if ($max < $min) {
            return $min;
        }

        return max($min, min($value, $max));
    }

    private function resolveMaxLineWidth(int $canvasW, int $padding, int $x, int $wrapWidthChars, int $fontSize): int
    {
        $inner = max(80, $canvasW - ($padding * 2));
        $wrapPx = (int) round($wrapWidthChars * $fontSize * 0.55);
        $fromSetting = min($inner, $wrapPx);
        $fromPosition = max(80, $canvasW - $padding - $x);

        return max(1, min($fromSetting, $fromPosition));
    }

    /**
     * @return list<string>
     */
    private function buildWrappedLines(string $title, string $fontPath, int $fontSize, string $hexColor, int $maxLineWidth): array
    {
        $font = FontFactory::build(function (FontFactory $factory) use ($fontPath, $fontSize, $hexColor, $maxLineWidth) {
            $factory->filename($fontPath);
            $factory->size($fontSize);
            $factory->color($hexColor);
            $factory->align('left', 'top');
            $factory->wrap($maxLineWidth);
        });

        $processor = new FontProcessor;
        $block = $processor->textBlock($title, $font, new Point(0, 0));
        $lines = [];

        foreach ($block as $line) {
            foreach ($this->splitLineToMaxWidth((string) $line, $font, $processor, $maxLineWidth) as $part) {
                $lines[] = $part;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function splitLineToMaxWidth(string $line, FontInterface $font, FontProcessor $processor, int $maxWidth): array
    {
        if ($line === '' || $processor->boxSize($line, $font)->width() <= $maxWidth) {
            return [$line];
        }

        $parts = [];
        $current = '';

        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $test = $current.$char;

            if ($current === '' || $processor->boxSize($test, $font)->width() <= $maxWidth) {
                $current = $test;

                continue;
            }

            $parts[] = $current;
            $current = $char;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts !== [] ? $parts : [$line];
    }

    private function parseRgb(string $value, array $default): array
    {
        $parts = array_map('trim', explode(',', $value));

        if (count($parts) !== 3) {
            return $default;
        }

        return array_map(fn ($p) => max(0, min(255, (int) $p)), $parts);
    }

    private function fallbackFont(): string
    {
        $candidates = [
            'C:/Windows/Fonts/arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('Font dosyası bulunamadı. storage/app/fonts/Urbanist-Black.ttf yükleyin.');
    }

    private function drawHeaderTitle(ImageInterface $image, string $title, array $settings, int $canvasW, int $canvasH): void
    {
        $headerSettings = array_merge($settings, [
            'font_size' => (int) ($settings['header_font_size'] ?? 36),
            'text_y' => (int) ($settings['header_text_y'] ?? 120),
            'wrap_width' => (int) ($settings['header_wrap_width'] ?? 30),
        ]);

        $this->drawBoundedTitle($image, $title, $headerSettings, $canvasW, $canvasH);
    }

    private function drawPayloadGrid(ImageInterface $image, Article $article, array $settings, int $canvasW, int $canvasH): void
    {
        $payload = $article->payload ?? [];

        if ($payload === []) {
            throw new \RuntimeException('Görsel üretimi için payload verisi bulunamadı.');
        }

        $padding = (int) ($settings['padding'] ?? 60);
        $startX = (int) ($settings['grid_start_x'] ?? 80);
        $startY = (int) ($settings['grid_start_y'] ?? 280);
        $rowHeight = (int) ($settings['row_height'] ?? 52);
        $labelFontSize = (int) ($settings['label_font_size'] ?? 26);
        $valueFontSize = (int) ($settings['value_font_size'] ?? 24);
        $columnXs = $settings['column_x'] ?? [80, 420, 680];
        $color = $this->parseRgb((string) ($settings['title_color'] ?? '255,255,255'), [255, 255, 255]);
        $hexColor = sprintf('#%02x%02x%02x', $color[0], $color[1], $color[2]);

        $rows = match ($article->type) {
            ArticleType::Gold => $this->goldGridRows($payload),
            ArticleType::Weather => $this->weatherGridRows($payload),
            default => [],
        };

        if ($rows === []) {
            throw new \RuntimeException('Payload grid satırları oluşturulamadı.');
        }

        $bottomLimit = $canvasH - $padding;
        $requiredHeight = $startY + (count($rows) * $rowHeight);

        if ($requiredHeight > $bottomLimit) {
            throw new \RuntimeException(
                'Grid taşması: '.count($rows).' satır × '.$rowHeight.'px canvas sınırını aşıyor.'
            );
        }

        $fontPath = $this->resolveFontPath();
        $y = $startY;

        foreach ($rows as $row) {
            foreach ($row as $colIndex => $cell) {
                $x = (int) ($columnXs[$colIndex] ?? ($startX + ($colIndex * 200)));
                $fontSize = $colIndex === 0 ? $labelFontSize : $valueFontSize;

                $image->text((string) $cell, $x, $y, function (FontFactory $font) use ($fontPath, $fontSize, $hexColor) {
                    $font->filename($fontPath);
                    $font->size($fontSize);
                    $font->color($hexColor);
                    $font->align('left', 'top');
                });
            }

            $y += $rowHeight;
        }
    }

    private function drawGoldSlots(ImageInterface $image, Article $article, array $settings, int $canvasW, int $canvasH): void
    {
        $payload = $article->payload ?? [];
        $items = $payload['items'] ?? [];

        if ($items === []) {
            throw new \RuntimeException('Altın fiyat payload verisi bulunamadı.');
        }

        $slots = $settings['gold_slots'] ?? ImageTemplate::defaultGoldSlots($canvasW, $canvasH);
        $fontSize = (int) ($settings['value_font_size'] ?? 22);
        $footerTimeFontSize = (int) ($settings['footer_time_font_size'] ?? max(14, $fontSize - 4));
        $footerDateFontSize = (int) ($settings['footer_date_font_size'] ?? max(14, $fontSize - 4));
        $color = $this->parseRgb((string) ($settings['value_color'] ?? $settings['title_color'] ?? '160,25,35'), [160, 25, 35]);
        $hexColor = sprintf('#%02x%02x%02x', $color[0], $color[1], $color[2]);
        $fontPath = $this->resolveFontPath();

        foreach ($items as $index => $item) {
            if (! isset($slots[$index])) {
                break;
            }

            $slot = $slots[$index];
            $this->drawSlotText($image, (string) ($item['purchase'] ?? ''), $slot['purchase'] ?? [], $fontSize, $hexColor, $fontPath);
            $this->drawSlotText($image, (string) ($item['sale'] ?? ''), $slot['sale'] ?? [], $fontSize, $hexColor, $fontPath);
        }

        if (filled($payload['source_updated_at'] ?? null) && isset($settings['footer_source_updated'])) {
            $this->drawSlotText(
                $image,
                (string) $payload['source_updated_at'],
                $settings['footer_source_updated'],
                $footerTimeFontSize,
                $hexColor,
                $fontPath,
            );
        }

        if ($article->data_fetched_at) {
            $fetchedText = $article->data_fetched_at->timezone('Europe/Istanbul')->format('d.m.Y');
            if (isset($settings['footer_data_fetched'])) {
                $this->drawSlotText(
                    $image,
                    $fetchedText,
                    $settings['footer_data_fetched'],
                    $footerDateFontSize,
                    $hexColor,
                    $fontPath,
                );
            }
        }
    }

    private function drawWeatherSlots(ImageInterface $image, Article $article, array $settings, int $canvasW, int $canvasH): void
    {
        $payload = $article->payload ?? [];
        $districts = $payload['districts'] ?? [];

        if ($districts === []) {
            throw new \RuntimeException('Hava durumu payload verisi bulunamadı.');
        }

        $slots = $settings['weather_slots'] ?? ImageTemplate::defaultWeatherSlots($canvasW, $canvasH);
        $valueFontSize = (int) ($settings['value_font_size'] ?? 20);
        $merkezTemperatureFontSize = (int) ($settings['merkez_temperature_font_size'] ?? 42);
        $headerDateFontSize = (int) ($settings['header_date_font_size'] ?? 24);
        $color = $this->parseRgb((string) ($settings['value_color'] ?? $settings['title_color'] ?? '30,50,90'), [30, 50, 90]);
        $hexColor = sprintf('#%02x%02x%02x', $color[0], $color[1], $color[2]);
        $fontPath = $this->resolveFontPath();

        foreach ($districts as $index => $district) {
            if (! isset($slots[$index])) {
                break;
            }

            $slot = $slots[$index];
            $temperatureFontSize = $index === 0 ? $merkezTemperatureFontSize : $valueFontSize;

            $this->drawSlotText(
                $image,
                ($district['temperature'] ?? '—').'°C',
                $slot['temperature'] ?? [],
                $temperatureFontSize,
                $hexColor,
                $fontPath,
            );

            $this->drawSlotText(
                $image,
                '%'.(string) ($district['humidity'] ?? '—'),
                $slot['humidity'] ?? [],
                $valueFontSize,
                $hexColor,
                $fontPath,
            );

            $this->drawSlotText(
                $image,
                ($district['wind_speed'] ?? '—').' km/s',
                $slot['wind_speed'] ?? [],
                $valueFontSize,
                $hexColor,
                $fontPath,
            );
        }

        if ($article->data_fetched_at && isset($settings['header_date'])) {
            $this->drawSlotText(
                $image,
                $article->data_fetched_at->timezone('Europe/Istanbul')->format('d.m.Y'),
                $settings['header_date'],
                $headerDateFontSize,
                $hexColor,
                $fontPath,
            );
        }
    }

    /**
     * @param  array{x?: int, y?: int, align?: string}  $slot
     */
    private function drawSlotText(
        ImageInterface $image,
        string $text,
        array $slot,
        int $fontSize,
        string $hexColor,
        string $fontPath,
    ): void {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $x = (int) ($slot['x'] ?? 0);
        $y = (int) ($slot['y'] ?? 0);
        $align = in_array($slot['align'] ?? 'center', ['left', 'center', 'right'], true)
            ? $slot['align']
            : 'center';
        $valign = in_array($slot['valign'] ?? 'center', ['top', 'center', 'bottom'], true)
            ? $slot['valign']
            : 'center';

        $image->text($text, $x, $y, function (FontFactory $font) use ($fontPath, $fontSize, $hexColor, $align, $valign) {
            $font->filename($fontPath);
            $font->size($fontSize);
            $font->color($hexColor);
            $font->align($align, $valign);
        });
    }

    /**
     * @return list<list<string>>
     */
    private function goldGridRows(array $payload): array
    {
        $rows = [];

        foreach ($payload['items'] ?? [] as $item) {
            $rows[] = [
                (string) ($item['purchase'] ?? ''),
                (string) ($item['sale'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function weatherGridRows(array $payload): array
    {
        $rows = [['İlçe', 'Sıcaklık', 'Nem', 'Rüzgar']];

        foreach ($payload['districts'] ?? [] as $district) {
            $rows[] = [
                (string) ($district['name'] ?? ''),
                ($district['temperature'] ?? '—').'°C',
                '%'.(string) ($district['humidity'] ?? '—'),
                ($district['wind_speed'] ?? '—').' km/s',
            ];
        }

        return $rows;
    }

    private function resolveFontPath(): string
    {
        $fontPath = storage_path('app/fonts/Urbanist-Black.ttf');
        if (is_file($fontPath)) {
            return $fontPath;
        }

        return $this->fallbackFont();
    }
}
