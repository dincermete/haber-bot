@php
    use App\Models\ImageTemplate;

    $initCw = (int) ($this->data['canvas_width'] ?? 1080);
    $initCh = (int) ($this->data['canvas_height'] ?? 1920);
    $weatherSlots = data_get($this->data, 'settings.weather_slots');
    if (! is_array($weatherSlots) || $weatherSlots === []) {
        $weatherSlots = ImageTemplate::defaultWeatherSlots($initCw, $initCh);
    }
    $headerDate = data_get($this->data, 'settings.header_date', ['x' => (int) round($initCw * 0.5), 'y' => (int) round($initCh * 0.094)]);
    $coordRows = ImageTemplate::weatherCoordinateTableRows($weatherSlots, $headerDate);
@endphp

<div class="col-span-full">
    <livewire:weather-template-coordinates-table
        :rows="$coordRows"
        :canvas-width="$initCw"
        :canvas-height="$initCh"
        :key="'weather-coords-'.$initCw.'-'.$initCh"
    />
</div>
