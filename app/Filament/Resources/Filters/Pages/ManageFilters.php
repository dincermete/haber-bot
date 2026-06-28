<?php

namespace App\Filament\Resources\Filters\Pages;

use App\Filament\Resources\Filters\FilterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFilters extends ManageRecords
{
    protected static string $resource = FilterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
