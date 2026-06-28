<?php

namespace App\Filament\Resources\Feeds\Pages;

use App\Filament\Resources\Feeds\FeedResource;
use App\Services\RssFeedService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageFeeds extends ManageRecords
{
    protected static string $resource = FeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    if (empty($data['title']) && ! empty($data['url'])) {
                        try {
                            $result = app(RssFeedService::class)->fetch($data['url']);
                            $data['title'] = $result['title'] ?: $data['url'];
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('RSS doğrulanamadı')
                                ->body($e->getMessage())
                                ->warning()
                                ->send();
                            $data['title'] = $data['url'];
                        }
                    }

                    return $data;
                }),
        ];
    }
}
