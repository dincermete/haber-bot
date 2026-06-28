@php
    $url = $url ?? '';
    $isSelected = $isSelected ?? false;
@endphp

<article @class([
    'haber-pool-card',
    'haber-pool-card--selected' => $isSelected,
])>
    <button
        type="button"
        class="haber-pool-card__hide"
        wire:click.stop="hideGalleryImage(@js($url))"
        title="Havuzdan gizle"
        aria-label="Gizle"
    >
        <x-filament::icon icon="heroicon-m-x-mark" class="fi-icon fi-size-sm" />
    </button>

    <button
        type="button"
        class="haber-pool-card__select"
        wire:click="selectGalleryImage(@js($url))"
        title="Kapak olarak seç"
    >
        <img
            src="{{ $url }}"
            alt="Galeri görseli"
            class="haber-pool-card__img"
            loading="lazy"
            onerror="this.closest('.haber-pool-card')?.remove()"
        />
        @if ($isSelected)
            <span class="haber-pool-card__selected-label">Kapak</span>
        @endif
    </button>

    <div class="haber-pool-card__actions">
        <button
            type="button"
            class="haber-pool-card__download"
            wire:click="downloadGalleryImage(@js($url))"
            wire:loading.attr="disabled"
            wire:target="downloadGalleryImage"
            title="Görseli indir"
        >
            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="fi-icon fi-size-sm" />
            <span wire:loading.remove wire:target="downloadGalleryImage">İndir</span>
            <span wire:loading wire:target="downloadGalleryImage">…</span>
        </button>
    </div>
</article>
