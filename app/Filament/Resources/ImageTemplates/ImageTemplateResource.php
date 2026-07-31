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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Utilities\Get;
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
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set) {
                            if ($state && str_contains($state, 'altin')) {
                                $set('settings.template_mode', 'gold');
                            }
                            if ($state && str_contains($state, 'hava')) {
                                $set('settings.template_mode', 'weather');
                            }
                        }),
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
                Section::make('Şablon Türü')
                    ->schema([
                        Select::make('settings.template_mode')
                            ->label('Şablon modu')
                            ->options([
                                'news' => 'Haber (tek başlık)',
                                'gold' => 'Altın fiyatları (hücre hücre)',
                                'weather' => 'Hava durumu (hücre hücre)',
                            ])
                            ->default('news')
                            ->native(false)
                            ->live(),
                    ]),
                Section::make('Haber Başlık Koordinatları')
                    ->visible(fn (Get $get): bool => ($get('settings.template_mode') ?? 'news') === 'news')
                    ->schema([
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
                Section::make('Altın Fiyat Düzenleyici')
                    ->description('Ürün adları şablonda olduğu için yalnızca alış/satış fiyatlarını ve alt bilgi alanlarını sürükleyerek konumlandırın.')
                    ->visible(fn (Get $get): bool => ($get('settings.template_mode') ?? 'news') === 'gold')
                    ->schema([
                        TextInput::make('settings.value_font_size')
                            ->label('Fiyat font boyutu (tuval px)')
                            ->helperText('Üretilen PNG\'deki gerçek boyut. Önizleme ile birebir aynıdır.')
                            ->numeric()
                            ->default(22)
                            ->live(debounce: 300),
                        TextInput::make('settings.value_color')
                            ->label('Fiyat rengi (R,G,B)')
                            ->default('160,25,35')
                            ->live(debounce: 500),
                        TextInput::make('settings.footer_time_font_size')
                            ->label('Kaynak güncelleme font (saat, tuval px)')
                            ->numeric()
                            ->default(28)
                            ->live(debounce: 300),
                        TextInput::make('settings.footer_date_font_size')
                            ->label('Veri çekimi font (tarih, tuval px)')
                            ->numeric()
                            ->default(26)
                            ->live(debounce: 300),
                        Hidden::make('settings.gold_coordinates_json')
                            ->dehydrated(),
                        View::make('filament.components.gold-template-coordinate-editor')
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Koordinatlar')
                    ->description(fn (Get $get): string => sprintf(
                        'Tuval: %d×%d px. Sürükleyerek değiştirdiğiniz konumlar burada anlık güncellenir. Kaydetmek için sayfanın üstündeki Kaydet butonuna basın.',
                        (int) ($get('canvas_width') ?? 941),
                        (int) ($get('canvas_height') ?? 1796),
                    ))
                    ->visible(fn (Get $get): bool => ($get('settings.template_mode') ?? 'news') === 'gold')
                    ->schema([
                        View::make('filament.components.gold-template-coordinates-panel')
                            ->columnSpanFull(),
                    ]),
                Section::make('Hava Durumu Düzenleyici')
                    ->description('İlçe adları şablonda olduğu için yalnızca sıcaklık, nem, rüzgar ve tarih alanlarını sürükleyerek konumlandırın.')
                    ->visible(fn (Get $get): bool => ($get('settings.template_mode') ?? 'news') === 'weather')
                    ->schema([
                        TextInput::make('settings.value_font_size')
                            ->label('Değer font boyutu (tuval px)')
                            ->helperText('İlçe satırlarındaki nem ve rüzgar değerleri.')
                            ->numeric()
                            ->default(20)
                            ->live(debounce: 300),
                        TextInput::make('settings.merkez_temperature_font_size')
                            ->label('Merkez sıcaklık font (tuval px)')
                            ->numeric()
                            ->default(42)
                            ->live(debounce: 300),
                        TextInput::make('settings.header_date_font_size')
                            ->label('Tarih font (tuval px)')
                            ->numeric()
                            ->default(24)
                            ->live(debounce: 300),
                        TextInput::make('settings.value_color')
                            ->label('Değer rengi (R,G,B)')
                            ->default('30,50,90')
                            ->live(debounce: 500),
                        Hidden::make('settings.weather_coordinates_json')
                            ->dehydrated(),
                        View::make('filament.components.weather-template-coordinate-editor')
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Koordinatlar')
                    ->description(fn (Get $get): string => sprintf(
                        'Tuval: %d×%d px. Sürükleyerek veya tablodan girerek değiştirdiğiniz konumlar burada anlık güncellenir. Kaydetmek için sayfanın üstündeki Kaydet butonuna basın.',
                        (int) ($get('canvas_width') ?? 1080),
                        (int) ($get('canvas_height') ?? 1920),
                    ))
                    ->visible(fn (Get $get): bool => ($get('settings.template_mode') ?? 'news') === 'weather')
                    ->schema([
                        View::make('filament.components.weather-template-coordinates-panel')
                            ->columnSpanFull(),
                    ]),
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
