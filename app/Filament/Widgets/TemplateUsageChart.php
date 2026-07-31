<?php

namespace App\Filament\Widgets;

use App\Models\ImageTemplate;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class TemplateUsageChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Şablon Kullanım Dağılımı';

    protected ?string $description = 'Görsel üretiminde kullanılan şablonların oransal payı';

    protected ?string $maxHeight = '320px';

    protected static bool $isLazy = false;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $templates = ImageTemplate::query()
            ->withCount(['articles' => fn ($query) => $query->whereNotNull('generated_image_path')])
            ->orderByDesc('articles_count')
            ->get();

        $labels = $templates->pluck('name')->all();
        $data = $templates->pluck('articles_count')->all();

        $palette = [
            'rgba(245, 158, 11, 0.85)',
            'rgba(59, 130, 246, 0.85)',
            'rgba(16, 185, 129, 0.85)',
            'rgba(139, 92, 246, 0.85)',
            'rgba(236, 72, 153, 0.85)',
            'rgba(14, 165, 233, 0.85)',
            'rgba(234, 88, 12, 0.85)',
            'rgba(107, 114, 128, 0.85)',
        ];

        $backgroundColors = collect($data)
            ->keys()
            ->map(fn (int $index): string => $palette[$index % count($palette)])
            ->all();

        return [
            'datasets' => [
                [
                    'label' => 'Kullanım',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array | RawJs
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => RawJs::make(<<<'JS'
                            function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.parsed;
                                const pct = total ? Math.round((value / total) * 100) : 0;
                                return context.label + ': ' + value + ' (' + pct + '%)';
                            }
                        JS),
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
