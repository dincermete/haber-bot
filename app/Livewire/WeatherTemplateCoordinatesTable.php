<?php

namespace App\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Livewire\Component;

class WeatherTemplateCoordinatesTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @var array<string, array{coordinate_key?: string, label: string, field: string, x: int, y: int}> */
    public array $rows = [];

    public int $canvasWidth = 1080;

    public int $canvasHeight = 1920;

    /**
     * @param  array<string, array{coordinate_key?: string, label: string, field: string, x: int, y: int}>  $rows
     */
    public function mount(array $rows = [], int $canvasWidth = 1080, int $canvasHeight = 1920): void
    {
        $this->rows = $this->normalizeRows($rows);
        $this->canvasWidth = $canvasWidth;
        $this->canvasHeight = $canvasHeight;
    }

    /**
     * @param  array<string, array{coordinate_key?: string, label: string, field: string, x: int, y: int}>  $rows
     */
    #[On('weather-coordinates-updated')]
    public function updateRows(array $rows): void
    {
        $this->rows = $this->normalizeRows($rows);
        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->rows)
            ->columns([
                TextColumn::make('label')
                    ->label('İlçe / Alan')
                    ->wrap(),
                TextColumn::make('field')
                    ->label('Tür')
                    ->badge()
                    ->color(fn (array $record): string => match ($record['field']) {
                        'Sıcaklık' => 'warning',
                        'Nem' => 'info',
                        'Rüzgar' => 'success',
                        default => 'gray',
                    }),
                TextInputColumn::make('x')
                    ->label('X')
                    ->type('number')
                    ->inputMode('numeric')
                    ->step(1)
                    ->alignEnd()
                    ->rules(['required', 'integer', 'min:0'])
                    ->updateStateUsing(fn (array $record, mixed $state): int => $this->updateCoordinate($record, 'x', $state)),
                TextInputColumn::make('y')
                    ->label('Y')
                    ->type('number')
                    ->inputMode('numeric')
                    ->step(1)
                    ->alignEnd()
                    ->rules(['required', 'integer', 'min:0'])
                    ->updateStateUsing(fn (array $record, mixed $state): int => $this->updateCoordinate($record, 'y', $state)),
            ])
            ->paginated(false);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function updateCoordinate(array $record, string $axis, mixed $state): int
    {
        $key = (string) ($record['coordinate_key'] ?? $record['__key'] ?? '');
        $value = max(0, (int) $state);
        $value = min($value, $axis === 'x' ? $this->canvasWidth : $this->canvasHeight);

        if ($key !== '' && isset($this->rows[$key])) {
            $this->rows[$key][$axis] = $value;
            $this->rows[$key]['coordinate_key'] = $key;
        }

        $this->flushCachedTableRecords();
        $this->dispatch('weather-coordinate-input-updated', rows: $this->normalizeRows($this->rows));

        return $value;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeRows(array $rows): array
    {
        foreach ($rows as $key => $row) {
            $rows[$key]['coordinate_key'] = (string) ($row['coordinate_key'] ?? $key);
            $rows[$key]['x'] = (int) ($row['x'] ?? 0);
            $rows[$key]['y'] = (int) ($row['y'] ?? 0);
        }

        return $rows;
    }

    public function render()
    {
        return view('livewire.weather-template-coordinates-table');
    }
}
