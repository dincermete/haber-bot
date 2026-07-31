@php
    $generatedUrl = $generatedUrl ?? null;
    $coverUrl = $coverUrl ?? null;
    $hasGenerated = filled($generatedUrl);
    $hasCover = filled($coverUrl);
@endphp

<div class="haber-preview">
    <div wire:loading.flex wire:target="generateImage" class="haber-preview__state">
        <x-filament::loading-indicator class="fi-icon fi-size-lg" />
        <p class="haber-preview__state-title">Görsel üretiliyor…</p>
        <p class="haber-preview__state-sub">Kapak, şablon ve başlık birleştiriliyor</p>
    </div>

    <div wire:loading.remove wire:target="generateImage">
        @if ($hasGenerated)
            <button
                type="button"
                class="haber-preview__frame haber-preview__frame--interactive"
                wire:click="downloadGeneratedImage"
                wire:loading.attr="disabled"
                wire:target="downloadGeneratedImage"
                title="Tıklayarak indir"
            >
                <img src="{{ $generatedUrl }}" alt="Üretilmiş haber görseli" class="haber-preview__img" draggable="false" />
                <span class="haber-preview__badge haber-preview__badge--ready">Üretildi · indir</span>
            </button>
        @elseif ($hasCover)
            <div class="haber-preview__frame">
                <img src="{{ $coverUrl }}" alt="Seçili kapak" class="haber-preview__img haber-preview__img--cover" />
                <div class="haber-preview__overlay">
                    <x-filament::icon icon="heroicon-o-photo" class="fi-icon fi-size-lg" />
                    <p class="haber-preview__overlay-title">Görsel henüz üretilmedi</p>
                    <p class="haber-preview__overlay-sub">«Görseli Üret» ile şablon uygulanır</p>
                </div>
            </div>
        @else
            <div class="haber-preview__state">
                <x-filament::icon icon="heroicon-o-photo" class="fi-icon fi-size-xl" />
                <p class="haber-preview__state-title">Kapak seçilmedi</p>
                <p class="haber-preview__state-sub">Alttaki havuzdan bir görsel seçin</p>
            </div>
        @endif
    </div>
</div>
