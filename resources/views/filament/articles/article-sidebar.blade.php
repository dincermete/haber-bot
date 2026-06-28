@php
    $previewUrl = $previewUrl ?? null;
    $isGenerating = $isGenerating ?? false;
    $hasGenerated = filled($previewUrl);
@endphp

<div class="col-span-full">
    <div
        @class([
            'overflow-hidden rounded-xl border border-gray-200 bg-gray-950 dark:border-gray-700',
            'ring-2 ring-primary-500/40' => $hasGenerated,
        ])
    >
        @if ($isGenerating)
            <div class="flex min-h-[280px] flex-col items-center justify-center gap-3 p-8 text-center">
                <x-filament::loading-indicator class="h-8 w-8 text-primary-500" />
                <p class="text-sm font-medium text-gray-300">Görsel birleştiriliyor…</p>
                <p class="text-xs text-gray-500">Kapak + şablon + başlık tek PNG olarak kaydediliyor</p>
            </div>
        @elseif ($hasGenerated)
            <button
                type="button"
                wire:click="downloadGeneratedImage"
                wire:loading.attr="disabled"
                wire:target="downloadGeneratedImage"
                title="Tıklayarak indir"
                class="group block w-full cursor-pointer text-left transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500 disabled:cursor-wait disabled:opacity-70"
            >
                <img
                    src="{{ $previewUrl }}"
                    alt="Üretilmiş haber görseli"
                    class="block w-full select-none"
                    draggable="false"
                />
                <p class="border-t border-gray-800 px-3 py-2 text-center text-xs text-gray-500 group-hover:text-gray-400">
                    <span wire:loading.remove wire:target="downloadGeneratedImage">Tıklayarak indir · başlık veya kapak değişince «Görseli Üret» ile yenileyin</span>
                    <span wire:loading wire:target="downloadGeneratedImage">İndiriliyor…</span>
                </p>
            </button>
        @else
            <div class="flex min-h-[200px] flex-col items-center justify-center gap-2 p-8 text-center">
                <p class="text-sm text-gray-400">Henüz görsel üretilmedi</p>
                <p class="text-xs text-gray-500">«Görseli Üret» ile kapak, şablon ve başlık tek dosyada birleştirilir</p>
            </div>
        @endif
    </div>
</div>
