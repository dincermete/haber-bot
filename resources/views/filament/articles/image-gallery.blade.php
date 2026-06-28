@php
    $gallery = $galleryImages ?? [];
    $selected = $selectedImageUrl ?? null;
    $count = count($gallery);
@endphp

<div class="col-span-full space-y-4" wire:key="gallery-{{ md5($selected ?? '') }}-{{ $count }}">
    {{-- Seçili kapak — büyük önizleme --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
        @if ($selected)
            <div class="flex items-center justify-between border-b border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                <span class="text-xs font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">Kapak görseli</span>
                <span class="text-[10px] text-gray-500">Tıklayarak indir</span>
            </div>
            <button
                type="button"
                wire:click="downloadGalleryImage(@js($selected))"
                wire:loading.attr="disabled"
                wire:target="downloadGalleryImage"
                title="Kapak görselini indir"
                class="group flex aspect-video w-full cursor-pointer items-center justify-center bg-gray-950/5 p-2 transition hover:bg-gray-950/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500 disabled:cursor-wait dark:bg-black/20 dark:hover:bg-black/30"
            >
                <img
                    src="{{ $selected }}"
                    alt="Seçili kapak"
                    class="max-h-48 max-w-full rounded-lg object-contain shadow-sm transition group-hover:scale-[1.01]"
                />
            </button>
        @else
            <div class="flex aspect-video flex-col items-center justify-center gap-2 p-6 text-center">
                <x-filament::icon icon="heroicon-o-hand-raised" class="h-7 w-7 text-gray-400" />
                <p class="text-sm text-gray-500">Aşağıdan bir kapak görseli seçin</p>
            </div>
        @endif
    </div>

    @if ($count === 0)
        <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center dark:border-gray-600">
            <p class="text-sm text-gray-500">Kaynak siteden görsel bulunamadı.</p>
            <p class="mt-1 text-xs text-gray-400">Dosya yükleyerek kapak ekleyebilirsiniz.</p>
        </div>
    @else
        <div>
            <div class="mb-2 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-950 dark:text-white">Galeri</span>
                <span class="text-xs text-gray-500">{{ $count }} görsel</span>
            </div>

            {{-- Yatay kaydırılabilir şerit — sabit boyutlu kartlar --}}
            <div
                class="flex gap-2.5 overflow-x-auto pb-1 pt-0.5"
                style="scrollbar-width: thin;"
            >
                @foreach ($gallery as $index => $url)
                    @php
                        $isSelected = $selected === $url;
                    @endphp
                    <div
                        @class([
                            'group relative shrink-0 overflow-hidden rounded-lg',
                            'ring-2 ring-primary-500 shadow-md' => $isSelected,
                            'ring-1 ring-gray-200 dark:ring-gray-600' => ! $isSelected,
                        ])
                        style="width: 5.5rem; height: 5.5rem;"
                    >
                        <button
                            type="button"
                            wire:click="downloadGalleryImage(@js($url))"
                            wire:loading.attr="disabled"
                            wire:target="downloadGalleryImage"
                            title="Görseli indir"
                            class="relative h-full w-full cursor-pointer overflow-hidden transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500 disabled:cursor-wait"
                        >
                            <img
                                src="{{ $url }}"
                                alt="Galeri {{ $index + 1 }}"
                                class="h-full w-full object-cover"
                                loading="lazy"
                                onerror="this.closest('[style]').remove()"
                            />
                            <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/0 transition group-hover:bg-black/35">
                                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4 text-white opacity-0 transition group-hover:opacity-100" />
                            </span>
                            @if ($isSelected)
                                <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-primary-600 py-0.5 text-center text-[10px] font-semibold text-white">
                                    Kapak
                                </span>
                            @endif
                        </button>
                        @if (! $isSelected)
                            <button
                                type="button"
                                wire:click.stop="selectGalleryImage(@js($url))"
                                title="Kapak olarak seç"
                                class="absolute bottom-1 left-1/2 z-10 -translate-x-1/2 rounded bg-white/95 px-1.5 py-0.5 text-[9px] font-semibold text-gray-800 shadow opacity-0 transition group-hover:opacity-100 focus:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:bg-gray-900/95 dark:text-gray-100"
                            >
                                Seç
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500">Görsele tıklayarak indirin · «Seç» ile kapak belirleyin</p>
        </div>
    @endif
</div>
