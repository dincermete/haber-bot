<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Ad Soyad')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): ?string => $record->is(Auth::user())
                        ? 'Giriş yaptığınız hesap'
                        : null),
                TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Güncellenme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (User $record): bool => $record->is(Auth::user()))
                    ->tooltip(fn (User $record): ?string => $record->is(Auth::user())
                        ? 'Kendi hesabınızı silemezsiniz'
                        : null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records): void {
                            if ($records->contains(fn (User $user): bool => $user->is(Auth::user()))) {
                                Notification::make()
                                    ->title('Kendi hesabınızı silemezsiniz')
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('Henüz kullanıcı yok')
            ->emptyStateDescription('Yeni bir sistem kullanıcısı ekleyerek başlayın.')
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }
}
