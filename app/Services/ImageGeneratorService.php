<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ImageTemplate;
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

        $canvas = $this->buildBackgroundLayer($imageUrl, $referer, $canvasW, $canvasH, $settings);
        $canvas = $this->overlayDesignFrame($canvas, $template, $canvasW, $canvasH);
        $this->drawBoundedTitle($canvas, $article->title ?: 'Başlıksız', $settings, $canvasW, $canvasH);

        $relativePath = 'articles/generated/'.$article->id.'.png';
        Storage::disk('public')->makeDirectory('articles/generated');
        $canvas->save(Storage::disk('public')->path($relativePath));

        return $relativePath;
    }

    private function buildBackgroundLayer(?string $imageUrl, ?string $referer, int $width, int $height, array $settings): ImageInterface
    {
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
}
