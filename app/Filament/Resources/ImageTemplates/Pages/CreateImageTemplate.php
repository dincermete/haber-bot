<?php

namespace App\Filament\Resources\ImageTemplates\Pages;

use App\Filament\Resources\ImageTemplates\ImageTemplateResource;
use App\Models\ImageTemplate;
use Filament\Resources\Pages\CreateRecord;

class CreateImageTemplate extends CreateRecord
{
    protected static string $resource = ImageTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['settings'])) {
            $data['settings'] = ImageTemplate::defaultSettings();
        }

        if (is_array($data['file_path'] ?? null)) {
            $data['file_path'] = (string) reset($data['file_path']);
        }

        if (! empty($data['is_default'])) {
            ImageTemplate::query()->update(['is_default' => false]);
        }

        return $data;
    }
}
