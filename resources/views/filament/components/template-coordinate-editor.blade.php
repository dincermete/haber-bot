@php
    $filePath = $this->data['file_path'] ?? null;
    if (is_array($filePath) && ! empty($filePath)) {
        $filePath = (string) reset($filePath);
    }
    $templateUrl = is_string($filePath) && $filePath !== ''
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($filePath)
        : '';

    $titleColor = data_get($this->data, 'settings.title_color', '255,255,255');
    $rgb = array_map('intval', array_pad(explode(',', (string) $titleColor), 3, 255));
    $textColorCss = sprintf('rgb(%d, %d, %d)', $rgb[0], $rgb[1], $rgb[2]);
    $initX = (int) data_get($this->data, 'settings.text_x', 60);
    $initY = (int) data_get($this->data, 'settings.text_y', 720);
    $initFs = (int) data_get($this->data, 'settings.font_size', 48);
    $initPad = (int) data_get($this->data, 'settings.padding', 60);
    $initWrap = (int) data_get($this->data, 'settings.wrap_width', 40);
    $initCw = (int) ($this->data['canvas_width'] ?? 1080);
    $initCh = (int) ($this->data['canvas_height'] ?? 1080);
@endphp

<div class="col-span-full rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
    <div
        wire:ignore
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('template-coordinate-editor', 'admin') }}"
        x-data="templateCoordinateEditor($wire)"
        data-canvas-w="{{ $initCw }}"
        data-canvas-h="{{ $initCh }}"
        data-pos-x="{{ $initX }}"
        data-pos-y="{{ $initY }}"
        data-font-size="{{ $initFs }}"
        data-padding="{{ $initPad }}"
        data-wrap-width="{{ $initWrap }}"
        data-text-color="{{ $textColorCss }}"
        data-template-url="{{ $templateUrl }}"
        class="space-y-3"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Başlık Konumu</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    Metni sürükleyip bırakın veya tuvalde boş alana tıklayın.
                </p>
            </div>
            <div class="flex items-center gap-2 font-mono text-sm">
                <span class="rounded-md bg-gray-100 px-2.5 py-1 dark:bg-gray-800">
                    X <strong data-tce-x>{{ $initX }}</strong> px
                </span>
                <span class="rounded-md bg-gray-100 px-2.5 py-1 dark:bg-gray-800">
                    Y <strong data-tce-y>{{ $initY }}</strong> px
                </span>
            </div>
        </div>

        <div class="w-full max-w-2xl">
            <div
                data-tce-placeholder
                @class([
                    'flex min-h-48 items-center justify-center rounded-lg border border-dashed border-gray-300 text-sm text-gray-500 dark:border-gray-600',
                    'hidden' => $templateUrl !== '',
                ])
            >
                PNG şablon yükleyin — önizleme anında güncellenir
            </div>

            <div
                data-tce-canvas
                @class([
                    'relative w-full cursor-crosshair overflow-hidden rounded-lg border border-gray-300 bg-gray-900 shadow-inner dark:border-gray-600',
                    'hidden' => $templateUrl === '',
                ])
            >
                <div data-tce-stage class="absolute left-0 top-0 origin-top-left">
                    <img
                        data-tce-image
                        src="{{ $templateUrl }}"
                        alt="Şablon"
                        class="pointer-events-none block h-full w-full select-none object-fill"
                        draggable="false"
                    />

                    <div
                        data-tce-padding-guide
                        class="pointer-events-none absolute z-10 box-border border-2 border-dashed border-amber-400/80"
                        aria-hidden="true"
                    ></div>

                    <div
                        data-tce-label
                        class="tce-label absolute z-20 cursor-grab touch-none select-none font-black active:cursor-grabbing"
                    >Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation.</div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@900&display=swap" rel="stylesheet">
        <style>
            [data-tce-canvas] {
                position: relative;
            }

            [data-tce-stage] {
                position: absolute;
                left: 0;
                top: 0;
                transform-origin: top left;
            }

            [data-tce-padding-guide] {
                box-sizing: border-box;
            }

            [data-tce-label].tce-label {
                position: absolute !important;
                margin: 0 !important;
                padding: 0 !important;
                z-index: 20;
                font-family: 'Urbanist', 'Arial Black', sans-serif;
                font-weight: 900 !important;
                text-shadow: 0 2px 8px rgba(0, 0, 0, 0.9);
                pointer-events: auto;
                white-space: normal;
                word-break: break-word;
            }

            [data-tce-label].tce-label.is-dragging {
                cursor: grabbing;
                outline: 2px solid rgb(245 158 11);
                outline-offset: 2px;
            }
        </style>
    @endpush
@endonce
