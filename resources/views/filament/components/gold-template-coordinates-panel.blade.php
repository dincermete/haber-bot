@php
    use App\Models\ImageTemplate;

    $initCw = (int) ($this->data['canvas_width'] ?? 941);
    $initCh = (int) ($this->data['canvas_height'] ?? 1796);
    $goldSlots = data_get($this->data, 'settings.gold_slots');
    if (! is_array($goldSlots) || $goldSlots === []) {
        $goldSlots = ImageTemplate::defaultGoldSlots($initCw, $initCh);
    }
    $footerSource = data_get($this->data, 'settings.footer_source_updated', ['x' => (int) round($initCw * 0.35), 'y' => (int) round($initCh * 0.88)]);
    $footerFetched = data_get($this->data, 'settings.footer_data_fetched', ['x' => (int) round($initCw * 0.72), 'y' => (int) round($initCh * 0.88)]);
    $coordRows = ImageTemplate::goldCoordinateTableRows($goldSlots, $footerSource, $footerFetched);
@endphp

<div class="col-span-full">
    <livewire:gold-template-coordinates-table
        :rows="$coordRows"
        :canvas-width="$initCw"
        :canvas-height="$initCh"
        :key="'gold-coords-'.$initCw.'-'.$initCh"
    />
</div>
