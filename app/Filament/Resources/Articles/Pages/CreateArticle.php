<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Services\ActivityLogger;
use App\Services\AiService;
use App\Services\TemplateService;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    public ?string $aiInput = null;

    public ?string $aiMode = 'keyword';

    public function generateWithAi(): void
    {
        if (! $this->aiInput) {
            Notification::make()->title('URL veya anahtar kelime girin')->warning()->send();

            return;
        }

        try {
            $result = app(AiService::class)->generateFromPrompt($this->aiInput, $this->aiMode);

            $this->data['title'] = $result['title'];
            $this->data['summary'] = app(AiService::class)->plainTextToHtml($result['summary']);

            if ($this->aiMode === 'url') {
                $this->data['source_url'] = $this->aiInput;
            }

            Notification::make()->title('AI içerik oluşturuldu')->success()->send();
        } catch (\Throwable $e) {
            app(ActivityLogger::class)->log('AI hatası: '.$e->getMessage(), 'error');
            Notification::make()->title('AI hatası')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateAi')
                ->label('AI ile Doldur')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('info')
                ->form([
                    Select::make('aiMode')
                        ->label('Kaynak türü')
                        ->options(['url' => 'Haber URL\'si', 'keyword' => 'Anahtar kelime'])
                        ->default('keyword')
                        ->native(false),
                    TextInput::make('aiInput')
                        ->label('Girdi')
                        ->required()
                        ->placeholder('https://... veya anahtar kelime'),
                ])
                ->action(function (array $data) {
                    $this->aiMode = $data['aiMode'];
                    $this->aiInput = $data['aiInput'];
                    $this->generateWithAi();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 12])
                    ->schema([
                        Grid::make(1)
                            ->columnSpan(['default' => 1, 'lg' => 8])
                            ->schema([
                                Section::make('Haber İçeriği')
                                    ->description('Başlık ve özet — AI ile doldurabilir veya elle yazabilirsiniz')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Başlık')
                                            ->required()
                                            ->columnSpanFull(),
                                        RichEditor::make('summary')
                                            ->label('Özet')
                                            ->columnSpanFull()
                                            ->toolbarButtons([
                                                ['bold', 'italic', 'underline', 'link'],
                                                ['bulletList', 'orderedList'],
                                                ['undo', 'redo'],
                                            ]),
                                    ]),
                            ]),

                        Grid::make(1)
                            ->columnSpan(['default' => 1, 'lg' => 4])
                            ->schema([
                                Section::make('Kaynak')
                                    ->icon(Heroicon::OutlinedLink)
                                    ->schema([
                                        TextInput::make('source_name')
                                            ->label('Kaynak adı')
                                            ->default('Manuel')
                                            ->required(),
                                        TextInput::make('source_url')
                                            ->label('Kaynak URL')
                                            ->url()
                                            ->placeholder('https://...')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Görsel Şablonu')
                                    ->icon(Heroicon::OutlinedPhoto)
                                    ->schema([
                                        Select::make('image_template_id')
                                            ->label('Şablon')
                                            ->options(fn () => app(TemplateService::class)->optionsForSelect())
                                            ->placeholder('Varsayılan')
                                            ->native(false)
                                            ->searchable(),
                                    ]),

                                View::make('filament.articles.create-hint')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['feed_id'] = null;
        $data['article_uid'] = hash('sha256', Str::uuid()->toString());
        $data['original_title'] = $data['title'] ?? '';
        $data['original_content'] = trim(($data['title'] ?? '')."\n\n".($data['summary'] ?? ''));
        $data['link'] = $data['source_url'] ?? '';
        $data['status'] = ArticleStatus::Pending;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ArticleResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
