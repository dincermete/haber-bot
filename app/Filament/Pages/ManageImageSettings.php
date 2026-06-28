<?php



namespace App\Filament\Pages;



use App\Models\Setting;
use App\Services\TelegramService;
use BackedEnum;

use Filament\Actions\Action;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

use Filament\Notifications\Notification;

use Filament\Pages\Page;

use Filament\Schemas\Components\EmbeddedSchema;

use Filament\Schemas\Components\Form;

use Filament\Schemas\Components\Section;

use Filament\Schemas\Schema;

use Filament\Support\Icons\Heroicon;



class ManageImageSettings extends Page

{

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;



    protected static ?string $navigationLabel = 'Ayarlar';



    protected static ?string $title = 'Ayarlar';



    protected static ?int $navigationSort = 6;



    protected static ?string $slug = 'settings';



    /** @var array<string, mixed>|null */

    public ?array $data = [];



    public function mount(): void

    {

        $values = [];

        foreach (Setting::defaults() as $key => $default) {

            $values[$key] = Setting::get($key, $default);

        }



        $this->form->fill($values);

    }



    public function defaultForm(Schema $schema): Schema

    {

        return $schema->statePath('data');

    }



    public function form(Schema $schema): Schema

    {

        return $schema

            ->components([

                Section::make('RSS Tarama')->schema([

                    TextInput::make('scan_interval_minutes')

                        ->label('Tarama Sıklığı (dk)')

                        ->numeric()

                        ->helperText('RSS kaynaklarının ne sıklıkla taranacağını belirler.'),

                ]),

                Section::make('Telegram Bildirimleri')
                    ->description('RSS taramasında bulunan her yeni haber (filtreyi geçen) Telegram kanalına/grubuna düşer.')
                    ->schema([
                        Toggle::make('telegram_enabled')
                            ->label('Telegram bildirimleri açık')
                            ->default(false),
                        TextInput::make('telegram_bot_token')
                            ->label('Bot Token')
                            ->password()
                            ->revealable()
                            ->placeholder('123456789:ABC...')
                            ->columnSpanFull(),
                        TextInput::make('telegram_chat_id')
                            ->label('Chat / Kanal ID')
                            ->placeholder('-1001234567890 veya @kanal')
                            ->columnSpanFull(),
                        Toggle::make('telegram_send_photo')
                            ->label('Varsa kapak görselini ekle')
                            ->default(true)
                            ->helperText('Görsel gönderilemezse metin mesajı gönderilir.'),
                    ])
                    ->columns(2),

                Section::make('Yapay Zeka (OpenAI)')

                    ->description('Sistem promptlarını sol menüden AI Promptları sayfasında düzenleyebilirsiniz.')

                    ->schema([

                    TextInput::make('ai_openai_api_key')

                        ->label('OpenAI API Key')

                        ->password()

                        ->revealable()

                        ->helperText('Boş bırakılırsa .env OPENAI_API_KEY kullanılır.'),

                    TextInput::make('ai_openai_model')

                        ->label('Model')

                        ->default('gpt-4o-mini'),

                ])->columns(2),

                Section::make('Görsel Varsayılanları')->schema([

                    TextInput::make('image_canvas_width')->label('Tuval Genişlik')->numeric(),

                    TextInput::make('image_canvas_height')->label('Tuval Yükseklik')->numeric(),

                    TextInput::make('text_x')->label('Başlık X (fallback)')->numeric(),

                    TextInput::make('text_y')->label('Başlık Y (fallback)')->numeric(),

                    TextInput::make('image_title_font_size')->label('Font Boyutu (fallback)')->numeric(),

                    TextInput::make('image_padding')->label('Kenar Boşluğu (fallback)')->numeric(),

                    TextInput::make('image_title_wrap_width')->label('Satır Genişliği (fallback)')->numeric(),

                    TextInput::make('image_title_color')->label('Başlık Rengi (R,G,B)'),

                    TextInput::make('image_default_bg_color')->label('Varsayılan Arka Plan (R,G,B)'),

                ])

                    ->description('Şablon bazlı koordinatlar için Görsel Şablonları sayfasını kullanın.')

                    ->columns(2),

            ]);

    }



    public function content(Schema $schema): Schema

    {

        return $schema->components([

            Form::make([

                EmbeddedSchema::make('form'),

            ])

                ->id('settings-form')

                ->livewireSubmitHandler('save'),

        ]);

    }



    protected function getHeaderActions(): array
    {
        return [
            Action::make('testTelegram')
                ->label('Telegram Test')
                ->color('gray')
                ->action(function (): void {
                    try {
                        app(TelegramService::class)->sendTestMessage();
                        Notification::make()->title('Test mesajı gönderildi')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Telegram hatası')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('save')
                ->label('Kaydet')
                ->action('save'),
        ];
    }



    public function save(): void

    {

        $data = $this->form->getState();



        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            Setting::set($key, (string) ($value ?? ''));
        }



        Notification::make()->title('Ayarlar kaydedildi')->success()->send();

    }

}


