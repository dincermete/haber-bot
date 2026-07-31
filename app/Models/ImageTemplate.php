<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ImageTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'file_path',
        'is_default',
        'sort_order',
        'canvas_width',
        'canvas_height',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function getPreviewUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public static function defaultSettings(): array
    {
        return [
            'text_x' => (int) Setting::get('text_x', 60),
            'text_y' => (int) Setting::get('text_y', 720),
            'font_size' => (int) Setting::get('image_title_font_size', 48),
            'padding' => (int) Setting::get('image_padding', 60),
            'wrap_width' => (int) Setting::get('image_title_wrap_width', 40),
            'title_color' => (string) Setting::get('image_title_color', '255,255,255'),
            'default_bg_color' => (string) Setting::get('image_default_bg_color', '30,30,40'),
            'grid_start_x' => 80,
            'grid_start_y' => 280,
            'row_height' => 52,
            'label_font_size' => 26,
            'value_font_size' => 24,
            'header_font_size' => 34,
            'header_text_y' => 100,
            'header_wrap_width' => 28,
            'column_x' => [80, 420, 680],
        ];
    }

    public static function goldGridSettings(): array
    {
        return array_merge(self::defaultSettings(), [
            'template_mode' => 'gold',
            'default_bg_color' => '255,255,255',
            'value_font_size' => 36,
            'footer_time_font_size' => 28,
            'footer_date_font_size' => 26,
            'value_color' => '160,25,35',
            'gold_slots' => self::defaultGoldSlots(941, 1796),
            'footer_source_updated' => ['x' => 330, 'y' => 1580, 'align' => 'left', 'valign' => 'center'],
            'footer_data_fetched' => ['x' => 680, 'y' => 1580, 'align' => 'left', 'valign' => 'center'],
        ]);
    }

    /**
     * @return list<string>
     */
    public static function goldRowLabels(): array
    {
        return [
            '24 AYAR HAS',
            '22 AYAR',
            '14 AYAR',
            'BEŞLİ',
            'ATA LİRA',
            'YARIM',
            'ÇEYREK',
            '24 AYAR 1 GRAM',
        ];
    }

    /**
     * @param  list<array{purchase?: array{x?: int, y?: int}, sale?: array{x?: int, y?: int}}>  $goldSlots
     * @param  array{x?: int, y?: int}  $footerSource
     * @param  array{x?: int, y?: int}  $footerFetched
     * @return array<string, array{coordinate_key: string, label: string, field: string, x: int, y: int}>
     */
    public static function goldCoordinateTableRows(
        array $goldSlots,
        array $footerSource,
        array $footerFetched,
    ): array {
        $rows = [];

        foreach (self::goldRowLabels() as $index => $label) {
            $slot = $goldSlots[$index] ?? [];
            $purchase = $slot['purchase'] ?? ['x' => 0, 'y' => 0];
            $sale = $slot['sale'] ?? ['x' => 0, 'y' => 0];

            $rows["g{$index}p"] = [
                'coordinate_key' => "g{$index}p",
                'label' => $label,
                'field' => 'Alış',
                'x' => (int) ($purchase['x'] ?? 0),
                'y' => (int) ($purchase['y'] ?? 0),
            ];

            $rows["g{$index}s"] = [
                'coordinate_key' => "g{$index}s",
                'label' => $label,
                'field' => 'Satış',
                'x' => (int) ($sale['x'] ?? 0),
                'y' => (int) ($sale['y'] ?? 0),
            ];
        }

        $rows['footer_src'] = [
            'coordinate_key' => 'footer_src',
            'label' => 'Kaynak güncelleme (saat)',
            'field' => '—',
            'x' => (int) ($footerSource['x'] ?? 0),
            'y' => (int) ($footerSource['y'] ?? 0),
        ];

        $rows['footer_fetch'] = [
            'coordinate_key' => 'footer_fetch',
            'label' => 'Veri çekimi (tarih)',
            'field' => '—',
            'x' => (int) ($footerFetched['x'] ?? 0),
            'y' => (int) ($footerFetched['y'] ?? 0),
        ];

        return $rows;
    }

    public static function defaultGoldSlots(int $canvasW = 941, int $canvasH = 1796): array
    {
        $rowStartY = (int) round($canvasH * 0.248);
        $rowHeight = (int) round($canvasH * 0.032);
        $purchaseX = (int) round($canvasW * 0.60);
        $saleX = (int) round($canvasW * 0.83);

        $slots = [];

        for ($i = 0; $i < 8; $i++) {
            $y = $rowStartY + ($i * $rowHeight);
            $slots[] = [
                'purchase' => ['x' => $purchaseX, 'y' => $y, 'align' => 'center', 'valign' => 'center'],
                'sale' => ['x' => $saleX, 'y' => $y, 'align' => 'center', 'valign' => 'center'],
            ];
        }

        return $slots;
    }

    public function isGoldMode(): bool
    {
        return ($this->settings['template_mode'] ?? null) === 'gold'
            || $this->slug === 'altin-fiyatlari';
    }

    public function ensureGoldSlotDefaults(): void
    {
        if (! $this->isGoldMode()) {
            return;
        }

        $settings = $this->settings ?? [];
        $w = (int) ($this->canvas_width ?: 941);
        $h = (int) ($this->canvas_height ?: 1796);

        if (empty($settings['gold_slots'])) {
            $settings['gold_slots'] = self::defaultGoldSlots($w, $h);
        }

        if (empty($settings['footer_source_updated'])) {
            $settings['footer_source_updated'] = [
                'x' => (int) round($w * 0.35),
                'y' => (int) round($h * 0.88),
                'align' => 'left',
                'valign' => 'center',
            ];
        }

        if (empty($settings['footer_data_fetched'])) {
            $settings['footer_data_fetched'] = [
                'x' => (int) round($w * 0.72),
                'y' => (int) round($h * 0.88),
                'align' => 'left',
                'valign' => 'center',
            ];
        }

        $settings['template_mode'] = 'gold';

        $this->settings = $settings;
    }

    public static function weatherGridSettings(): array
    {
        return array_merge(self::defaultSettings(), [
            'template_mode' => 'weather',
            'default_bg_color' => '255,255,255',
            'value_font_size' => 20,
            'merkez_temperature_font_size' => 42,
            'header_date_font_size' => 24,
            'value_color' => '30,50,90',
            'weather_slots' => self::defaultWeatherSlots(1080, 1920),
            'header_date' => ['x' => 540, 'y' => 180, 'align' => 'center', 'valign' => 'center'],
        ]);
    }

    /**
     * @return list<string>
     */
    public static function weatherDistrictLabels(): array
    {
        return array_column(config('elazig_districts', []), 'name');
    }

    /**
     * @return array<string, string>
     */
    public static function weatherFieldLabels(): array
    {
        return [
            'temperature' => 'Sıcaklık',
            'humidity' => 'Nem',
            'wind_speed' => 'Rüzgar',
        ];
    }

    /**
     * @return list<array{temperature: array{x: int, y: int, align: string, valign: string}, humidity: array{x: int, y: int, align: string, valign: string}, wind_speed: array{x: int, y: int, align: string, valign: string}}>
     */
    public static function defaultWeatherSlots(int $canvasW = 1080, int $canvasH = 1920): array
    {
        $districtCount = count(self::weatherDistrictLabels());
        $slots = [];

        $slots[] = [
            'temperature' => [
                'x' => (int) round($canvasW * 0.68),
                'y' => (int) round($canvasH * 0.195),
                'align' => 'center',
                'valign' => 'center',
            ],
            'humidity' => [
                'x' => (int) round($canvasW * 0.26),
                'y' => (int) round($canvasH * 0.215),
                'align' => 'left',
                'valign' => 'center',
            ],
            'wind_speed' => [
                'x' => (int) round($canvasW * 0.26),
                'y' => (int) round($canvasH * 0.235),
                'align' => 'left',
                'valign' => 'center',
            ],
        ];

        $gridStartY = (int) round($canvasH * 0.27);
        $rowHeight = (int) round($canvasH * 0.038);
        $tempX = (int) round($canvasW * 0.48);
        $humidityX = (int) round($canvasW * 0.60);
        $windX = (int) round($canvasW * 0.74);

        for ($i = 1; $i < $districtCount; $i++) {
            $y = $gridStartY + (($i - 1) * $rowHeight);
            $slots[] = [
                'temperature' => ['x' => $tempX, 'y' => $y, 'align' => 'center', 'valign' => 'center'],
                'humidity' => ['x' => $humidityX, 'y' => $y, 'align' => 'center', 'valign' => 'center'],
                'wind_speed' => ['x' => $windX, 'y' => $y, 'align' => 'center', 'valign' => 'center'],
            ];
        }

        return $slots;
    }

    /**
     * @param  list<array{temperature?: array{x?: int, y?: int}, humidity?: array{x?: int, y?: int}, wind_speed?: array{x?: int, y?: int}}>  $weatherSlots
     * @param  array{x?: int, y?: int}  $headerDate
     * @return array<string, array{coordinate_key: string, label: string, field: string, x: int, y: int}>
     */
    public static function weatherCoordinateTableRows(array $weatherSlots, array $headerDate): array
    {
        $rows = [];
        $districts = self::weatherDistrictLabels();
        $fields = self::weatherFieldLabels();
        $fieldKeys = ['temperature', 'humidity', 'wind_speed'];
        $fieldSuffixes = ['t', 'h', 'w'];

        foreach ($districts as $index => $district) {
            $slot = $weatherSlots[$index] ?? [];

            foreach ($fieldKeys as $fieldIndex => $fieldKey) {
                $coord = $slot[$fieldKey] ?? ['x' => 0, 'y' => 0];
                $key = "w{$index}{$fieldSuffixes[$fieldIndex]}";

                $rows[$key] = [
                    'coordinate_key' => $key,
                    'label' => $district,
                    'field' => $fields[$fieldKey],
                    'x' => (int) ($coord['x'] ?? 0),
                    'y' => (int) ($coord['y'] ?? 0),
                ];
            }
        }

        $rows['header_date'] = [
            'coordinate_key' => 'header_date',
            'label' => 'Tarih',
            'field' => '—',
            'x' => (int) ($headerDate['x'] ?? 0),
            'y' => (int) ($headerDate['y'] ?? 0),
        ];

        return $rows;
    }

    public function isWeatherMode(): bool
    {
        return ($this->settings['template_mode'] ?? null) === 'weather'
            || $this->slug === 'hava-durumu';
    }

    public function ensureWeatherSlotDefaults(): void
    {
        if (! $this->isWeatherMode()) {
            return;
        }

        $settings = $this->settings ?? [];
        $w = (int) ($this->canvas_width ?: 1080);
        $h = (int) ($this->canvas_height ?: 1920);

        if (empty($settings['weather_slots'])) {
            $settings['weather_slots'] = self::defaultWeatherSlots($w, $h);
        }

        if (empty($settings['header_date'])) {
            $settings['header_date'] = [
                'x' => (int) round($w * 0.5),
                'y' => (int) round($h * 0.094),
                'align' => 'center',
                'valign' => 'center',
            ];
        }

        $settings['template_mode'] = 'weather';

        $this->settings = $settings;
    }
}
