@php
    $type = $type ?? 'gallery';
    $label = $label ?? 'Görsel';
    $url = $url ?? null;
    $previewUrl = $previewUrl ?? null;
    $isGenerating = $isGenerating ?? false;
    $isSelected = $isSelected ?? false;
    $hasGenerated = filled($previewUrl);
    $isGenerated = $type === 'generated';
@endphp

<article @class([
    'haber-images-card',
    'haber-images-card--generated' => $isGenerated,
    'haber-images-card--ready' => $isGenerated && $hasGenerated,
    'haber-images-card--selected' => ! $isGenerated && $isSelected,
])>
    <div class="haber-images-card__label">{{ $label }}</div>

    @if ($isGenerated)
        @if ($isGenerating)
            <div class="haber-images-card__empty">
                <x-filament::loading-indicator class="fi-icon fi-size-md" />
                <span>Üretiliyor…</span>
            </div>
        @elseif ($hasGenerated)
            <button
                type="button"
                class="haber-images-card__media"
                wire:click="downloadGeneratedImage"
                wire:loading.attr="disabled"
                wire:target="downloadGeneratedImage"
                title="Üretilmiş görseli indir"
            >
                <img src="{{ $previewUrl }}" alt="Üretilmiş haber görseli" draggable="false" />
            </button>
        @else
            <div class="haber-images-card__empty">
                <x-filament::icon icon="heroicon-o-photo" class="fi-icon fi-size-md" />
                <span>«Görseli Üret»</span>
            </div>
        @endif

        <div class="haber-images-card__footer">
            <span class="haber-images-card__footer-text">{{ $hasGenerated ? 'İndir' : '—' }}</span>
        </div>
    @else
        <button
            type="button"
            class="haber-images-card__media"
            wire:click="downloadGalleryImage(@js($url))"
            wire:loading.attr="disabled"
            wire:target="downloadGalleryImage"
            title="Görseli indir"
        >
            <img
                src="{{ $url }}"
                alt="{{ $label }}"
                loading="lazy"
                onerror="this.closest('.haber-images-card')?.remove()"
            />
        </button>

        <div class="haber-images-card__footer">
            @if ($isSelected)
                <span class="haber-images-card__footer-text haber-images-card__footer-text--selected">Seçili</span>
            @else
                <button
                    type="button"
                    class="haber-images-card__select-btn"
                    wire:click="selectGalleryImage(@js($url))"
                    title="Kapak olarak seç"
                >
                    Seç
                </button>
            @endif
        </div>
    @endif
</article>
