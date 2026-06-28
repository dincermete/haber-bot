@php
    $previewUrl = $previewUrl ?? null;
    $isGenerating = $isGenerating ?? false;
    $hasGenerated = filled($previewUrl);
    $gallery = $galleryImages ?? [];
    $selected = $selectedImageUrl ?? null;
    $count = count($gallery);
@endphp

<div
    class="haber-images-panel"
    wire:key="images-{{ md5(($previewUrl ?? '').'-'.(int) $isGenerating.'-'.($selected ?? '').'-'.$count) }}"
>
    <div class="haber-images-panel__header">
        <span class="haber-images-panel__title">Görseller</span>
        <span class="haber-images-panel__hint">Yatay kaydır · tıkla: indir</span>
    </div>

    <div class="haber-images-strip">
        {{-- Üretilmiş görsel --}}
        <article @class([
            'haber-images-card',
            'haber-images-card--generated',
            'haber-images-card--ready' => $hasGenerated,
        ])>
            <div class="haber-images-card__label">Üretilmiş</div>

            @if ($isGenerating)
                <div class="haber-images-card__loading">
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
                    <img
                        src="{{ $previewUrl }}"
                        alt="Üretilmiş haber görseli"
                        draggable="false"
                    />
                </button>
            @else
                <div class="haber-images-card__empty">
                    <x-filament::icon icon="heroicon-o-photo" class="fi-icon fi-size-md" />
                    <span>«Görseli Üret»</span>
                </div>
            @endif

            <div class="haber-images-card__footer">
                @if ($hasGenerated)
                    <span class="haber-images-card__footer-text">İndir</span>
                @else
                    <span class="haber-images-card__footer-text">—</span>
                @endif
            </div>
        </article>

        {{-- Galeri --}}
        @forelse ($gallery as $index => $url)
            @php
                $isSelected = $selected === $url;
            @endphp
            <article @class([
                'haber-images-card',
                'haber-images-card--selected' => $isSelected,
            ])>
                <div class="haber-images-card__label">Kapak {{ $index + 1 }}</div>

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
                        alt="Kapak {{ $index + 1 }}"
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
            </article>
        @empty
            <div class="haber-images-panel__empty">
                Kaynak görseli yok<br>
                <small>Aşağıdan dosya yükleyin</small>
            </div>
        @endforelse
    </div>

    <p class="haber-images-panel__help">Sabit çerçeve · object-contain · «Seç» ile kapak belirle</p>
</div>
