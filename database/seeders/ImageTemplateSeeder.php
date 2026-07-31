<?php

namespace Database\Seeders;

use App\Models\ImageTemplate;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Intervention\Image\Laravel\Facades\Image;

class ImageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDefaultNewsTemplate();
        $this->seedSpecialTemplate(
            slug: 'altin-fiyatlari',
            name: 'Altın Fiyatları',
            bgHex: '#1a2332',
            settings: ImageTemplate::goldGridSettings(),
            sortOrder: 10,
        );
        $this->seedSpecialTemplate(
            slug: 'hava-durumu',
            name: 'Hava Durumu',
            bgHex: '#143250',
            settings: ImageTemplate::weatherGridSettings(),
            sortOrder: 11,
        );
    }

    private function seedDefaultNewsTemplate(): void
    {
        if (ImageTemplate::query()->where('slug', 'varsayilan-sablon')->exists()) {
            return;
        }

        $templatePath = 'sablon.png';
        $fullPath = storage_path('app/public/'.$templatePath);

        if (! is_file($fullPath)) {
            $source = base_path('../data/tasarim_sablonu.png');
            if (is_file($source)) {
                File::ensureDirectoryExists(dirname($fullPath));
                File::copy($source, $fullPath);
            }
        }

        $width = (int) Setting::get('image_canvas_width', 1080);
        $height = (int) Setting::get('image_canvas_height', 1080);

        if (is_file($fullPath)) {
            try {
                $img = Image::decode($fullPath);
                $width = $img->width();
                $height = $img->height();
            } catch (\Throwable) {
                // keep defaults
            }
        }

        ImageTemplate::query()->create([
            'name' => 'Varsayılan Şablon',
            'slug' => 'varsayilan-sablon',
            'file_path' => $templatePath,
            'is_default' => true,
            'sort_order' => 0,
            'canvas_width' => $width,
            'canvas_height' => $height,
            'settings' => ImageTemplate::defaultSettings(),
        ]);
    }

  /** @param array<string, mixed> $settings */
    private function seedSpecialTemplate(
        string $slug,
        string $name,
        string $bgHex,
        array $settings,
        int $sortOrder,
    ): void {
        if (ImageTemplate::query()->where('slug', $slug)->exists()) {
            return;
        }

        $width = 1080;
        $height = 1080;
        $relativePath = "templates/{$slug}.png";
        $fullPath = storage_path('app/public/'.$relativePath);

        File::ensureDirectoryExists(dirname($fullPath));

        $canvas = Image::createImage($width, $height)->fill($bgHex);
        $canvas->save($fullPath);

        ImageTemplate::query()->create([
            'name' => $name,
            'slug' => $slug,
            'file_path' => $relativePath,
            'is_default' => false,
            'sort_order' => $sortOrder,
            'canvas_width' => $width,
            'canvas_height' => $height,
            'settings' => $settings,
        ]);
    }
}
