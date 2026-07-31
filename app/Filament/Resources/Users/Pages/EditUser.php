<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (User $record): bool => $record->is(Auth::user()))
                ->tooltip(fn (User $record): ?string => $record->is(Auth::user())
                    ? 'Kendi hesabınızı silemezsiniz'
                    : null),
        ];
    }
}
