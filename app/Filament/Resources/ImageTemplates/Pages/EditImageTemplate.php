<?php

namespace App\Filament\Resources\ImageTemplates\Pages;

use App\Filament\Resources\ImageTemplates\ImageTemplateResource;
use App\Models\ImageTemplate;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImageTemplate extends EditRecord
{
    protected static string $resource = ImageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (is_array($data['file_path'] ?? null)) {
            $data['file_path'] = (string) reset($data['file_path']);
        }

        if (! empty($data['is_default'])) {
            ImageTemplate::query()
                ->where('id', '!=', $this->record->id)
                ->update(['is_default' => false]);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }
}
