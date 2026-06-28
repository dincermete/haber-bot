<?php

namespace App\Filament\Resources\SendLogs\Pages;

use App\Filament\Resources\SendLogs\SendLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSendLogs extends ManageRecords
{
    protected static string $resource = SendLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
