<?php

namespace App\Filament\Resources\Filters;

use App\Filament\Resources\Filters\Pages\ManageFilters;
use App\Models\Filter;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FilterResource extends Resource
{
    protected static ?string $model = Filter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static ?string $navigationLabel = 'Filtreler';

    protected static ?string $modelLabel = 'Filtre';

    protected static ?string $pluralModelLabel = 'Filtreler';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('keyword')
                    ->label('Anahtar Kelimeler')
                    ->helperText('Virgülle ayırın (örn: yapay zeka, AI)')
                    ->required()
                    ->columnSpanFull(),
                Radio::make('list_type')
                    ->label('Liste Tipi')
                    ->options([
                        'whitelist' => 'Beyaz Liste',
                        'blacklist' => 'Kara Liste',
                    ])
                    ->default('whitelist')
                    ->required(),
                Select::make('logic_mode')
                    ->label('Mantık')
                    ->options([
                        'or' => 'VEYA',
                        'and' => 'VE',
                    ])
                    ->default('or')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->label('Anahtar Kelimeler')
                    ->searchable(),
                TextColumn::make('list_type')
                    ->label('Tip')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'whitelist' => 'Beyaz Liste',
                        'blacklist' => 'Kara Liste',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('logic_mode')
                    ->label('Mantık')
                    ->formatStateUsing(fn (string $state) => strtoupper($state === 'and' ? 'VE' : 'VEYA')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFilters::route('/'),
        ];
    }
}
