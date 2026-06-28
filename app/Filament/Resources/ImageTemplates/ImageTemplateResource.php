<?php

namespace App\Filament\Resources\ImageTemplates;

use App\Filament\Resources\ImageTemplates\Pages\CreateImageTemplate;
use App\Filament\Resources\ImageTemplates\Pages\EditImageTemplate;
use App\Filament\Resources\ImageTemplates\Pages\ListImageTemplates;
use App\Models\ImageTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageTemplateResource extends Resource
{
    protected static ?string $model = ImageTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Görsel Şablonları';

    protected static ?string $modelLabel = 'Şablon';

    protected static ?string $pluralModelLabel = 'Görsel Şablonları';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Şablon Bilgileri')->schema([
                    TextInput::make('name')
                        ->label('Ad')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set) {
                            if ($state) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    FileUpload::make('file_path')
                        ->label('PNG Şablon')
                        ->disk('public')
                        ->directory('templates')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png'])
                        ->maxSize(10240)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (! $state) {
                                return;
                            }
                            $path = is_array($state) ? reset($state) : $state;
                            if (! is_string($path)) {
                                return;
                            }
                            $fullPath = Storage::disk('public')->path($path);
                            if (is_file($fullPath)) {
                                try {
                                    $img = Image::decode($fullPath);
                                    $sourceW = max(1, $img->width());
                                    $sourceH = max(1, $img->height());
                                    $exportW = 1080;
                                    $exportH = max(1, (int) round($exportW * $sourceH / $sourceW));
                                    $set('canvas_width', $exportW);
                                    $set('canvas_height', $exportH);
                                } catch (\Throwable) {
                                    // keep existing
                                }
                            }
                        }),
                    Toggle::make('is_default')
                        ->label('Varsayılan Şablon'),
                    TextInput::make('sort_order')
                        ->label('Sıralama')
                        ->numeric()
                        ->default(0),
                    TextInput::make('canvas_width')
                        ->label('Tuval Genişlik (px)')
                        ->numeric()
                        ->default(1080)
                        ->live(debounce: 300),
                    TextInput::make('canvas_height')
                        ->label('Tuval Yükseklik (px)')
                        ->numeric()
                        ->default(1080)
                        ->live(debounce: 300),
                ])->columns(2),
                Section::make('Koordinatlar')->schema([
                    TextInput::make('settings.text_x')
                        ->label('Başlık X')
                        ->numeric()
                        ->default(60)
                        ->live(debounce: 300),
                    TextInput::make('settings.text_y')
                        ->label('Başlık Y')
                        ->numeric()
                        ->default(720)
                        ->live(debounce: 300),
                    TextInput::make('settings.font_size')
                        ->label('Font Boyutu')
                        ->numeric()
                        ->default(48)
                        ->live(debounce: 300),
                    TextInput::make('settings.padding')
                        ->label('Kenar Boşluğu (px)')
                        ->numeric()
                        ->default(60)
                        ->live(debounce: 300),
                    TextInput::make('settings.wrap_width')
                        ->label('Satır Genişliği (karakter)')
                        ->numeric()
                        ->default(40)
                        ->live(debounce: 300),
                    TextInput::make('settings.title_color')
                        ->label('Başlık Rengi (R,G,B)')
                        ->default('255,255,255')
                        ->live(debounce: 500),
                    TextInput::make('settings.default_bg_color')
                        ->label('Varsayılan Arka Plan (R,G,B)')
                        ->default('30,30,40'),
                    View::make('filament.components.template-coordinate-editor')
                        ->columnSpanFull(),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Önizleme')
                    ->disk('public')
                    ->height(50),
                TextColumn::make('name')
                    ->label('Ad')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('canvas_width')
                    ->label('Boyut')
                    ->formatStateUsing(fn (ImageTemplate $record) => "{$record->canvas_width}×{$record->canvas_height}"),
                IconColumn::make('is_default')
                    ->label('Varsayılan')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImageTemplates::route('/'),
            'create' => CreateImageTemplate::route('/create'),
            'edit' => EditImageTemplate::route('/{record}/edit'),
        ];
    }
}
