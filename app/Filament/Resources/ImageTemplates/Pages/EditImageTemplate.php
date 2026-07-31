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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $record->ensureGoldSlotDefaults();
        $record->ensureWeatherSlotDefaults();

        if ($record->isGoldMode()) {
            $data['settings'] = array_merge(
                ImageTemplate::goldGridSettings(),
                $record->settings ?? [],
            );
            $data['settings']['template_mode'] = 'gold';

            $data['settings']['gold_coordinates_json'] = json_encode([
                'gold_slots' => $data['settings']['gold_slots'] ?? ImageTemplate::defaultGoldSlots(
                    (int) ($data['canvas_width'] ?? $record->canvas_width ?? 941),
                    (int) ($data['canvas_height'] ?? $record->canvas_height ?? 1796),
                ),
                'footer_source_updated' => $data['settings']['footer_source_updated'] ?? null,
                'footer_data_fetched' => $data['settings']['footer_data_fetched'] ?? null,
            ], JSON_THROW_ON_ERROR);
        }

        if ($record->isWeatherMode()) {
            $data['settings'] = array_merge(
                ImageTemplate::weatherGridSettings(),
                $record->settings ?? [],
            );
            $data['settings']['template_mode'] = 'weather';

            $data['settings']['weather_coordinates_json'] = json_encode([
                'weather_slots' => $data['settings']['weather_slots'] ?? ImageTemplate::defaultWeatherSlots(
                    (int) ($data['canvas_width'] ?? $record->canvas_width ?? 1080),
                    (int) ($data['canvas_height'] ?? $record->canvas_height ?? 1920),
                ),
                'header_date' => $data['settings']['header_date'] ?? null,
            ], JSON_THROW_ON_ERROR);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (is_array($data['file_path'] ?? null)) {
            $data['file_path'] = (string) reset($data['file_path']);
        }

        if (! empty($data['settings']['gold_coordinates_json'])) {
            $layout = json_decode((string) $data['settings']['gold_coordinates_json'], true);

            if (is_array($layout)) {
                if (isset($layout['gold_slots']) && is_array($layout['gold_slots'])) {
                    $data['settings']['gold_slots'] = $layout['gold_slots'];
                }

                if (isset($layout['footer_source_updated']) && is_array($layout['footer_source_updated'])) {
                    $data['settings']['footer_source_updated'] = $layout['footer_source_updated'];
                }

                if (isset($layout['footer_data_fetched']) && is_array($layout['footer_data_fetched'])) {
                    $data['settings']['footer_data_fetched'] = $layout['footer_data_fetched'];
                }
            }

            unset($data['settings']['gold_coordinates_json']);
        }

        if (! empty($data['settings']['weather_coordinates_json'])) {
            $layout = json_decode((string) $data['settings']['weather_coordinates_json'], true);

            if (is_array($layout)) {
                if (isset($layout['weather_slots']) && is_array($layout['weather_slots'])) {
                    $data['settings']['weather_slots'] = $layout['weather_slots'];
                }

                if (isset($layout['header_date']) && is_array($layout['header_date'])) {
                    $data['settings']['header_date'] = $layout['header_date'];
                }
            }

            unset($data['settings']['weather_coordinates_json']);
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
