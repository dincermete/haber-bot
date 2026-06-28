@php
    use App\Models\Setting;

    $template = $this->data['image_design_template'] ?? Setting::get('image_design_template', 'sablon.png');
    if (is_array($template) && ! empty($template)) {
        $template = basename((string) reset($template));
    }
    $templateUrl = \App\Filament\Pages\ManageImageSettings::templatePreviewUrl(
        is_string($template) && $template !== '' ? $template : 'sablon.png'
    );
    $titleColor = $this->data['image_title_color'] ?? Setting::get('image_title_color', '255,255,255');
    $rgb = array_map('intval', array_pad(explode(',', (string) $titleColor), 3, 255));
    $textColorCss = sprintf('rgb(%d, %d, %d)', $rgb[0], $rgb[1], $rgb[2]);
    $initX = (int) ($this->data['text_x'] ?? Setting::get('text_x', 60));
    $initY = (int) ($this->data['text_y'] ?? Setting::get('text_y', 720));
@endphp

<div class="col-span-full space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Koordinat Editörü</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Başlığı sürükleyin — konum anlık güncellenir. Kesikli çerçeve: güvenli alan (padding).
        </p>
    </div>

    <div
        wire:ignore
        x-data="{
            templateUrl: @js($templateUrl),
            textColor: @js($textColorCss),
            sampleTitle: 'Örnek Haber Başlığı Metni',
            dragging: false,
            offsetX: 0,
            offsetY: 0,
            containerWidth: 480,
            posX: {{ $initX }},
            posY: {{ $initY }},
            fontSize: $wire.$entangle('data.image_title_font_size', true),
            padding: $wire.$entangle('data.image_padding', true),
            canvasWidth: $wire.$entangle('data.image_canvas_width', true),
            canvasHeight: $wire.$entangle('data.image_canvas_height', true),
            num(v, fallback = 0) {
                const n = parseInt(v, 10);
                return Number.isFinite(n) ? n : fallback;
            },
            get cw() { return this.num(this.canvasWidth, 1080); },
            get ch() { return this.num(this.canvasHeight, 1080); },
            get pad() { return this.num(this.padding, 60); },
            get fs() { return this.num(this.fontSize, 48); },
            get scale() {
                const maxW = Math.max(240, this.containerWidth);
                return Math.min(maxW / this.cw, 0.55);
            },
            get previewW() { return Math.round(this.cw * this.scale); },
            get previewH() { return Math.round(this.ch * this.scale); },
            get boxStyle() {
                const s = this.scale;
                return {
                    left: (this.posX * s) + 'px',
                    top: (this.posY * s) + 'px',
                    fontSize: (this.fs * s) + 'px',
                    lineHeight: ((this.fs + 10) * s) + 'px',
                    maxWidth: (Math.max(80, this.cw - this.pad * 2) * s) + 'px',
                    color: this.textColor,
                };
            },
            get safeAreaStyle() {
                const s = this.scale;
                const p = this.pad * s;
                return { left: p + 'px', top: p + 'px', right: p + 'px', bottom: p + 'px' };
            },
            get crosshairH() {
                const s = this.scale;
                return { top: (this.posY * s) + 'px', left: '0', right: '0', height: '1px' };
            },
            get crosshairV() {
                const s = this.scale;
                return { left: (this.posX * s) + 'px', top: '0', bottom: '0', width: '1px' };
            },
            get markerStyle() {
                const s = this.scale;
                return { left: (this.posX * s) + 'px', top: (this.posY * s) + 'px' };
            },
            init() {
                this.measure();
                if (this.$refs.wrapper && typeof ResizeObserver !== 'undefined') {
                    new ResizeObserver(() => this.measure()).observe(this.$refs.wrapper);
                }
                this.$wire.$watch('data.text_x', (v) => {
                    if (! this.dragging) this.posX = this.num(v, this.posX);
                });
                this.$wire.$watch('data.text_y', (v) => {
                    if (! this.dragging) this.posY = this.num(v, this.posY);
                });
            },
            measure() {
                if (this.$refs.wrapper) {
                    this.containerWidth = this.$refs.wrapper.clientWidth;
                }
            },
            clampPosition(x, y) {
                const s = this.scale;
                const padPx = this.pad;
                const maxX = this.cw - this.pad - 40;
                const maxY = this.ch - this.pad - 40;
                return {
                    x: Math.max(padPx, Math.min(Math.round(x), maxX)),
                    y: Math.max(padPx, Math.min(Math.round(y), maxY)),
                };
            },
            pointer(e) {
                if (e.touches?.length) {
                    return { x: e.touches[0].clientX, y: e.touches[0].clientY };
                }
                return { x: e.clientX, y: e.clientY };
            },
            startDrag(e) {
                this.dragging = true;
                const rect = this.$refs.canvas.getBoundingClientRect();
                const p = this.pointer(e);
                this.offsetX = p.x - rect.left - (this.posX * this.scale);
                this.offsetY = p.y - rect.top - (this.posY * this.scale);
            },
            onDrag(e) {
                if (! this.dragging) return;
                const rect = this.$refs.canvas.getBoundingClientRect();
                const p = this.pointer(e);
                const s = this.scale;
                let dx = (p.x - rect.left - this.offsetX) / s;
                let dy = (p.y - rect.top - this.offsetY) / s;
                const clamped = this.clampPosition(dx, dy);
                this.posX = clamped.x;
                this.posY = clamped.y;
            },
            stopDrag() {
                if (! this.dragging) return;
                this.dragging = false;
                this.$wire.set('data.text_x', String(this.posX));
                this.$wire.set('data.text_y', String(this.posY));
            },
        }"
        @mousemove.window="onDrag($event)"
        @mouseup.window="stopDrag()"
        @touchmove.window.prevent="onDrag($event)"
        @touchend.window="stopDrag()"
        @touchcancel.window="stopDrag()"
        class="space-y-3"
    >
        {{-- Koordinat göstergesi — her zaman görünür --}}
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 font-mono text-sm dark:border-primary-800 dark:bg-primary-950">
                <span class="font-semibold text-primary-700 dark:text-primary-300">X</span>
                <span x-text="posX + ' px'" class="min-w-[3.5rem] text-right font-bold text-gray-900 dark:text-white"></span>
            </div>
            <div class="inline-flex items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 font-mono text-sm dark:border-primary-800 dark:bg-primary-950">
                <span class="font-semibold text-primary-700 dark:text-primary-300">Y</span>
                <span x-text="posY + ' px'" class="min-w-[3.5rem] text-right font-bold text-gray-900 dark:text-white"></span>
            </div>
            <span
                x-show="dragging"
                x-cloak
                class="animate-pulse rounded-lg bg-amber-100 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
            >
                ● Konumlandırılıyor…
            </span>
        </div>

        {{-- Tuval — sayfaya sığacak şekilde --}}
        <div x-ref="wrapper" class="w-full max-w-xl mx-auto">
            <div class="overflow-hidden rounded-lg border border-gray-300 bg-gray-950 p-2 dark:border-gray-600">
                <div
                    x-ref="canvas"
                    class="relative mx-auto select-none overflow-hidden bg-gray-800"
                    :style="{ width: previewW + 'px', height: previewH + 'px' }"
                    :class="dragging ? 'ring-2 ring-primary-400' : ''"
                >
                    <img
                        :src="templateUrl"
                        alt="Şablon"
                        class="pointer-events-none absolute inset-0 h-full w-full object-fill"
                        draggable="false"
                    />

                    {{-- Güvenli alan --}}
                    <div
                        class="pointer-events-none absolute border-2 border-dashed border-amber-400/60"
                        :style="safeAreaStyle"
                    ></div>

                    {{-- Sürüklerken nişangah çizgileri --}}
                    <div
                        x-show="dragging"
                        x-cloak
                        class="pointer-events-none absolute z-20 bg-primary-400/80"
                        :style="crosshairH"
                    ></div>
                    <div
                        x-show="dragging"
                        x-cloak
                        class="pointer-events-none absolute z-20 bg-primary-400/80"
                        :style="crosshairV"
                    ></div>

                    {{-- Başlık kutusu --}}
                    <div
                        @mousedown.prevent="startDrag($event)"
                        @touchstart.prevent="startDrag($event)"
                        :style="boxStyle"
                        class="absolute z-30 cursor-grab touch-none font-black active:cursor-grabbing"
                        :class="dragging
                            ? 'rounded-sm bg-black/30 px-1 outline outline-2 outline-primary-400 outline-offset-1'
                            : 'rounded-sm bg-black/20 px-1 hover:bg-black/30 hover:outline hover:outline-1 hover:outline-white/50'"
                        style="font-family: 'Urbanist', 'Arial Black', sans-serif; text-shadow: 0 2px 8px rgba(0,0,0,0.95);"
                    >
                        <span x-text="sampleTitle"></span>
                    </div>

                    {{-- Sol üst köşe işaretçisi — her zaman görünür --}}
                    <div
                        class="pointer-events-none absolute z-40 -translate-x-1/2 -translate-y-1/2"
                        :style="markerStyle"
                    >
                        <div
                            class="h-3 w-3 rounded-full border-2 border-white shadow-lg"
                            :class="dragging ? 'bg-primary-500 scale-125' : 'bg-primary-600'"
                        ></div>
                    </div>

                    {{-- Tuval üstü koordinat etiketi --}}
                    <div
                        class="pointer-events-none absolute right-2 top-2 z-40 rounded bg-black/75 px-2 py-1 font-mono text-xs text-white"
                        x-text="'(' + posX + ', ' + posY + ')'"
                    ></div>
                </div>
            </div>
            <p class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">
                Ölçek: <span x-text="Math.round(scale * 100)"></span>% — gerçek tuval <span x-text="cw"></span>×<span x-text="ch"></span> px
            </p>
        </div>
    </div>
</div>

@once
    @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@900&display=swap" rel="stylesheet">
        <style>[x-cloak]{display:none!important}</style>
    @endpush
@endonce
