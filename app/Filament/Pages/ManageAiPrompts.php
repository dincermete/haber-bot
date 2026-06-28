<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageAiPrompts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'AI Promptları';

    protected static ?string $title = 'AI Promptları';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'ai-prompts';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        Setting::ensureAiPrompts();

        $values = [];
        foreach (Setting::aiPromptDefaults() as $key => $default) {
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
                Section::make('Başlık Yeniden Yazma')
                    ->description('RSS ve haber düzenleme ekranındaki “AI ile başlık yaz” işlemi bu promptu kullanır.')
                    ->schema([
                        Textarea::make('ai_prompt_title')
                            ->label('Sistem Promptu')
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Özet Yeniden Yazma')
                    ->description('Haber düzenleme ekranındaki “AI ile özet yaz” işlemi bu promptu kullanır. Özetin sonuna Kaynak satırı ve 5 Instagram hashtag eklenir; kaynak adı haber kaydından otomatik gönderilir.')
                    ->schema([
                        Textarea::make('ai_prompt_summary')
                            ->label('Sistem Promptu')
                            ->rows(8)
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('URL / Anahtar Kelime ile Üretim')
                    ->description('Manuel haber oluşturma ekranında URL veya anahtar kelimeden içerik üretirken kullanılır.')
                    ->schema([
                        Textarea::make('ai_prompt_generate')
                            ->label('Sistem Promptu')
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([
                EmbeddedSchema::make('form'),
            ])
                ->id('ai-prompts-form')
                ->livewireSubmitHandler('save'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Kaydet')
                ->action('save'),
            Action::make('resetDefaults')
                ->label('Varsayılana Dön')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Promptları sıfırla')
                ->modalDescription('Tüm AI promptları fabrika varsayılanlarına dönecek. Devam edilsin mi?')
                ->action(function (): void {
                    foreach (Setting::aiPromptDefaults() as $key => $value) {
                        Setting::set($key, $value);
                    }

                    $this->mount();

                    Notification::make()->title('Promptlar varsayılana döndürüldü')->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (Setting::aiPromptKeys() as $key) {
            Setting::set($key, (string) ($data[$key] ?? ''));
        }

        Notification::make()->title('AI promptları kaydedildi')->success()->send();
    }
}
