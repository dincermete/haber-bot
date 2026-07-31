<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kullanıcı Bilgileri')
                    ->schema([
                        TextInput::make('name')
                            ->label('Ad Soyad')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('E-posta')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),
                Section::make('Güvenlik')
                    ->schema([
                        TextInput::make('password')
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Şifre' : 'Yeni Şifre')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->confirmed()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Boş bırakırsanız mevcut şifre korunur.'
                                : null),
                        TextInput::make('password_confirmation')
                            ->label('Şifre Tekrar')
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' || filled($get('password'))),
                    ])
                    ->columns(2),
            ]);
    }
}
