<?php

namespace App\Filament\Resources\ImageTemplates\Pages;

use App\Filament\Resources\ImageTemplates\ImageTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImageTemplates extends ListRecords
{
    protected static string $resource = ImageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
