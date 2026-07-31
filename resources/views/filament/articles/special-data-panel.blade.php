@php
    $payload = $payload ?? [];
    $type = $type ?? 'gold';
@endphp

<div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm">
    @if ($type === 'gold')
        <table class="w-full text-left">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 font-medium">Ürün</th>
                    <th class="px-3 py-2 font-medium">Alış</th>
                    <th class="px-3 py-2 font-medium">Satış</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payload['items'] ?? [] as $item)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2">{{ $item['label'] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $item['purchase'] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $item['sale'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if (filled($payload['source_updated_at'] ?? null))
            <p class="px-3 py-2 text-xs text-gray-500 border-t border-gray-100 dark:border-gray-700">
                Kaynak güncelleme: {{ $payload['source_updated_at'] }}
            </p>
        @endif
    @elseif ($type === 'weather')
        <table class="w-full text-left">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 font-medium">İlçe</th>
                    <th class="px-3 py-2 font-medium">Sıcaklık</th>
                    <th class="px-3 py-2 font-medium">Nem</th>
                    <th class="px-3 py-2 font-medium">Rüzgar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payload['districts'] ?? [] as $district)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2">{{ $district['name'] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $district['temperature'] ?? '—' }}°C</td>
                        <td class="px-3 py-2">%{{ $district['humidity'] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $district['wind_speed'] ?? '—' }} km/s</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($payload['stale'] ?? false)
            <p class="px-3 py-2 text-xs text-amber-600 border-t border-gray-100 dark:border-gray-700">
                {{ $payload['stale_note'] ?? 'Önceki veri kullanılıyor.' }}
            </p>
        @endif
    @endif

    @if (filled($dataFetchedAt ?? null))
        <p class="px-3 py-2 text-xs text-gray-500 border-t border-gray-100 dark:border-gray-700">
            Veri çekimi: {{ $dataFetchedAt }}
        </p>
    @endif
</div>
