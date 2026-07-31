@php
    use App\Models\ImageTemplate;

    $filePath = $this->data['file_path'] ?? null;
    if (is_array($filePath) && ! empty($filePath)) {
        $filePath = (string) reset($filePath);
    }
    $templateUrl = is_string($filePath) && $filePath !== ''
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($filePath)
        : '';

    $initCw = (int) ($this->data['canvas_width'] ?? 1080);
    $initCh = (int) ($this->data['canvas_height'] ?? 1920);
    $valueColor = data_get($this->data, 'settings.value_color', '30,50,90');
    $rgb = array_map('intval', array_pad(explode(',', (string) $valueColor), 3, 30));
    $textColorCss = sprintf('rgb(%d, %d, %d)', $rgb[0], $rgb[1], $rgb[2]);
    $initFs = (int) data_get($this->data, 'settings.value_font_size', 20);
    $initMerkezTempFs = (int) data_get($this->data, 'settings.merkez_temperature_font_size', 42);
    $initHeaderDateFs = (int) data_get($this->data, 'settings.header_date_font_size', 24);
    $weatherSlots = data_get($this->data, 'settings.weather_slots');
    if (! is_array($weatherSlots) || $weatherSlots === []) {
        $weatherSlots = ImageTemplate::defaultWeatherSlots($initCw, $initCh);
    }
    $headerDate = data_get($this->data, 'settings.header_date', ['x' => (int) round($initCw * 0.5), 'y' => (int) round($initCh * 0.094)]);
    $districtLabels = ImageTemplate::weatherDistrictLabels();
@endphp

<div class="col-span-full">
    <div
        wire:ignore
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('weather-template-coordinate-editor', 'admin') }}"
        x-data="weatherTemplateCoordinateEditor($wire)"
        data-canvas-w="{{ $initCw }}"
        data-canvas-h="{{ $initCh }}"
        data-font-size="{{ $initFs }}"
        data-merkez-temp-font-size="{{ $initMerkezTempFs }}"
        data-header-date-font-size="{{ $initHeaderDateFs }}"
        data-text-color="{{ $textColorCss }}"
        data-template-url="{{ $templateUrl }}"
        data-weather-slots='@json($weatherSlots)'
        data-header-date='@json($headerDate)'
        data-district-labels='@json($districtLabels)'
        class="space-y-4"
    >
        <div
            data-wtce-placeholder
            @class([
                'flex min-h-48 items-center justify-center rounded-lg border border-dashed border-gray-300 text-sm text-gray-500 dark:border-gray-600',
                'hidden' => $templateUrl !== '',
            ])
        >
            PNG şablon yükleyin
        </div>

        <div
            data-wtce-canvas
            style="aspect-ratio: {{ $initCw }} / {{ $initCh }};"
            @class([
                'wtce-canvas relative w-full max-h-[70vh] cursor-crosshair overflow-hidden rounded-lg border border-gray-300 bg-gray-100 shadow-inner dark:border-gray-600',
                'hidden' => $templateUrl === '',
            ])
        >
            <div data-wtce-stage class="wtce-stage">
                <img
                    data-wtce-image
                    src="{{ $templateUrl }}"
                    alt="Hava durumu şablonu"
                    class="wtce-template-image"
                    draggable="false"
                />
                <div data-wtce-markers class="wtce-markers"></div>
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>
            .wtce-canvas {
                position: relative !important;
                max-width: 22rem;
            }

            @media (min-width: 640px) {
                .wtce-canvas {
                    max-width: 24rem;
                }
            }

            .wtce-stage {
                position: relative;
                width: 100%;
                height: 100%;
            }

            .wtce-template-image {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: fill;
                pointer-events: none;
                user-select: none;
            }

            .wtce-markers {
                position: absolute;
                inset: 0;
                z-index: 10;
            }

            .wtce-marker {
                position: absolute;
                z-index: 20;
                cursor: grab;
                touch-action: none;
                user-select: none;
                font-weight: 700;
                white-space: nowrap;
                padding: 1px 4px;
                border-radius: 3px;
                background: rgba(255, 255, 255, 0.85);
                border: 1px dashed rgba(30, 50, 90, 0.6);
            }

            .wtce-marker.is-humidity {
                border-color: rgba(25, 80, 160, 0.6);
            }

            .wtce-marker.is-wind {
                border-color: rgba(25, 140, 80, 0.6);
            }

            .wtce-marker.is-header-date {
                border-color: rgba(180, 120, 0, 0.8);
                background: rgba(255, 248, 220, 0.9);
            }

            .wtce-marker.is-dragging {
                cursor: grabbing;
                outline: 2px solid rgb(245 158 11);
            }
        </style>
    @endpush
@endonce
