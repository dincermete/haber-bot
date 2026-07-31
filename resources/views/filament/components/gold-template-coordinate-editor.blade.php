@php
    use App\Models\ImageTemplate;

    $filePath = $this->data['file_path'] ?? null;
    if (is_array($filePath) && ! empty($filePath)) {
        $filePath = (string) reset($filePath);
    }
    $templateUrl = is_string($filePath) && $filePath !== ''
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($filePath)
        : '';

    $initCw = (int) ($this->data['canvas_width'] ?? 941);
    $initCh = (int) ($this->data['canvas_height'] ?? 1796);
    $valueColor = data_get($this->data, 'settings.value_color', '160,25,35');
    $rgb = array_map('intval', array_pad(explode(',', (string) $valueColor), 3, 160));
    $textColorCss = sprintf('rgb(%d, %d, %d)', $rgb[0], $rgb[1], $rgb[2]);
    $initFs = (int) data_get($this->data, 'settings.value_font_size', 22);
    $initFooterTimeFs = (int) data_get($this->data, 'settings.footer_time_font_size', 28);
    $initFooterDateFs = (int) data_get($this->data, 'settings.footer_date_font_size', 26);
    $goldSlots = data_get($this->data, 'settings.gold_slots');
    if (! is_array($goldSlots) || $goldSlots === []) {
        $goldSlots = ImageTemplate::defaultGoldSlots($initCw, $initCh);
    }
    $footerSource = data_get($this->data, 'settings.footer_source_updated', ['x' => (int) round($initCw * 0.35), 'y' => (int) round($initCh * 0.88)]);
    $footerFetched = data_get($this->data, 'settings.footer_data_fetched', ['x' => (int) round($initCw * 0.72), 'y' => (int) round($initCh * 0.88)]);
    $rowLabels = ImageTemplate::goldRowLabels();
@endphp

<div class="col-span-full">
    <div
        wire:ignore
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('gold-template-coordinate-editor', 'admin') }}"
        x-data="goldTemplateCoordinateEditor($wire)"
        data-canvas-w="{{ $initCw }}"
        data-canvas-h="{{ $initCh }}"
        data-font-size="{{ $initFs }}"
        data-footer-time-font-size="{{ $initFooterTimeFs }}"
        data-footer-date-font-size="{{ $initFooterDateFs }}"
        data-text-color="{{ $textColorCss }}"
        data-template-url="{{ $templateUrl }}"
        data-gold-slots='@json($goldSlots)'
        data-footer-source='@json($footerSource)'
        data-footer-fetched='@json($footerFetched)'
        data-row-labels='@json($rowLabels)'
        class="space-y-4"
    >
        <div
            data-gtce-placeholder
            @class([
                'flex min-h-48 items-center justify-center rounded-lg border border-dashed border-gray-300 text-sm text-gray-500 dark:border-gray-600',
                'hidden' => $templateUrl !== '',
            ])
        >
            PNG şablon yükleyin
        </div>

        <div
            data-gtce-canvas
            style="aspect-ratio: {{ $initCw }} / {{ $initCh }};"
            @class([
                'gtce-canvas relative w-full max-h-[70vh] cursor-crosshair overflow-hidden rounded-lg border border-gray-300 bg-gray-100 shadow-inner dark:border-gray-600',
                'hidden' => $templateUrl === '',
            ])
        >
            <div data-gtce-stage class="gtce-stage">
                <img
                    data-gtce-image
                    src="{{ $templateUrl }}"
                    alt="Altın şablonu"
                    class="gtce-template-image"
                    draggable="false"
                />
                <div data-gtce-markers class="gtce-markers"></div>
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>
            .gtce-canvas {
                position: relative !important;
                max-width: 22rem;
            }

            @media (min-width: 640px) {
                .gtce-canvas {
                    max-width: 24rem;
                }
            }

            .gtce-stage {
                position: relative;
                width: 100%;
                height: 100%;
            }

            .gtce-template-image {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: fill;
                pointer-events: none;
                user-select: none;
            }

            .gtce-markers {
                position: absolute;
                inset: 0;
                z-index: 10;
            }

            .gtce-marker {
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
                border: 1px dashed rgba(160, 25, 35, 0.6);
            }

            .gtce-marker.is-sale {
                border-color: rgba(25, 80, 160, 0.6);
            }

            .gtce-marker.is-footer {
                border-color: rgba(180, 120, 0, 0.8);
                background: rgba(255, 248, 220, 0.9);
            }

            .gtce-marker.is-dragging {
                cursor: grabbing;
                outline: 2px solid rgb(245 158 11);
            }
        </style>
    @endpush
@endonce
