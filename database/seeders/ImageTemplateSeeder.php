<?php

namespace Database\Seeders;

use App\Models\ImageTemplate;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (ImageTemplate::query()->exists()) {
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
}
