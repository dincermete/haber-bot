<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Komuta Merkezi';

    protected static ?string $title = 'Komuta Merkezi';

    public function getTitle(): string | Htmlable
    {
        return 'Komuta Merkezi';
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg' => 3,
        ];
    }
}
