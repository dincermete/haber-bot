<?php

namespace App\Services;

use App\Exceptions\WeatherFetchException;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class OpenMeteoWeatherService
{
    private const string BASE_URL = 'https://api.open-meteo.com/v1/forecast';

    /**
     * @return array{
     *     districts: list<array{name: string, temperature: float, humidity: int, wind_speed: float}>,
     *     fetched_at: string,
     *     stale: bool,
     *     stale_note: ?string
     * }
     */
    public function fetch(): array
    {
        $districts = config('elazig_districts', []);

        if (count($districts) !== 11) {
            throw new WeatherFetchException('Elazığ ilçe listesi eksik veya hatalı.');
        }

        $responses = Http::pool(function (Pool $pool) use ($districts) {
            $requests = [];

            foreach ($districts as $index => $district) {
                $requests[$district['name']] = $pool->as($district['name'])
                    ->timeout(15)
                    ->get(self::BASE_URL, [
                        'latitude' => $district['lat'],
                        'longitude' => $district['lng'],
                        'current' => 'temperature_2m,relative_humidity_2m,wind_speed_10m',
                        'timezone' => 'Europe/Istanbul',
                    ]);
            }

            return $requests;
        });

        $results = [];

        foreach ($districts as $district) {
            $name = $district['name'];
            $response = $responses[$name] ?? null;

            if ($response === null || ! $response->successful()) {
                throw new WeatherFetchException("{$name} ilçesi için hava durumu alınamadı.");
            }

            $current = $response->json('current');

            if (! is_array($current)
                || ! isset($current['temperature_2m'], $current['relative_humidity_2m'], $current['wind_speed_10m'])) {
                throw new WeatherFetchException("{$name} ilçesi için hava verisi eksik.");
            }

            $results[] = [
                'name' => $name,
                'temperature' => round((float) $current['temperature_2m'], 1),
                'humidity' => (int) $current['relative_humidity_2m'],
                'wind_speed' => round((float) $current['wind_speed_10m'], 1),
            ];
        }

        return [
            'districts' => $results,
            'fetched_at' => now()->timezone('Europe/Istanbul')->toIso8601String(),
            'stale' => false,
            'stale_note' => null,
        ];
    }

    public function buildTitle(array $payload): string
    {
        $date = now()->timezone('Europe/Istanbul')->format('d.m.Y');
        $merkez = collect($payload['districts'] ?? [])->firstWhere('name', 'Merkez');
        $temp = $merkez['temperature'] ?? '—';

        return "Elazığ Hava Durumu — {$date} (Merkez: {$temp}°C)";
    }

    public function buildSummaryHtml(array $payload): string
    {
        $lines = ['<p><strong>Elazığ ilçeleri</strong> anlık hava durumu:</p>', '<table border="1" cellpadding="6"><thead><tr><th>İlçe</th><th>Sıcaklık</th><th>Nem</th><th>Rüzgar</th></tr></thead><tbody>'];

        foreach ($payload['districts'] ?? [] as $district) {
            $lines[] = sprintf(
                '<tr><td>%s</td><td>%s°C</td><td>%%%d</td><td>%s km/s</td></tr>',
                e($district['name']),
                e((string) $district['temperature']),
                (int) $district['humidity'],
                e((string) $district['wind_speed']),
            );
        }

        $lines[] = '</tbody></table>';

        if (($payload['stale'] ?? false) && filled($payload['stale_note'] ?? null)) {
            $lines[] = '<p><em>'.e($payload['stale_note']).'</em></p>';
        }

        return implode("\n", $lines);
    }
}
