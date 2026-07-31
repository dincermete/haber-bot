<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Filament\Resources\Articles\ArticleResource;
use App\Services\ArticleImageRegenerator;
use App\Services\ActivityLogger;
use App\Services\AiService;
use App\Services\ArticleGalleryService;
use App\Services\ImageCacheService;
use App\Services\SpecialContentFactory;
use App\Services\TemplateService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInput\Actions\CopyAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected bool $isFirstApproval = false;

    protected bool $isInternalSave = false;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->status === ArticleStatus::Processing) {
            Notification::make()
                ->title('Haber işleniyor')
                ->body('RSS taraması tamamlanana kadar düzenlenemez.')
                ->warning()
                ->send();

            $this->redirect(ArticleResource::getUrl('index'));
        }
    }

    protected function internalSave(): void
    {
        $this->isInternalSave = true;

        try {
            $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
        } finally {
            $this->isInternalSave = false;
        }
    }

    public function selectGalleryImage(string $url): void
    {
        if (($this->data['selected_image_url'] ?? '') === $url) {
            return;
        }

        app(ArticleGalleryService::class)->selectImage($this->getRecord(), $url);
        $this->data['selected_image_url'] = $url;
        Notification::make()->title('Kapak güncellendi')->success()->send();
    }

    public function hideGalleryImage(string $url): void
    {
        $url = trim($url);
        if ($url === '') {
            return;
        }

        $gallery = array_values(array_filter(
            (array) ($this->data['gallery_images'] ?? []),
            fn (string $item): bool => trim($item) !== $url,
        ));

        $selected = (string) ($this->data['selected_image_url'] ?? '');
        if ($selected === $url) {
            $selected = $gallery[0] ?? '';
            $this->data['selected_image_url'] = $selected !== '' ? $selected : null;
        }

        $this->data['gallery_images'] = $gallery;

        $this->getRecord()->update([
            'gallery_images' => $gallery,
            'selected_image_url' => $this->data['selected_image_url'],
        ]);

        Notification::make()->title('Görsel havuzdan kaldırıldı')->success()->send();
    }

    #[On('upload:finished')]
    public function handleUploadedImageFinished(string $name, array $tmpFilenames): void
    {
        if (! str_starts_with($name, 'data.uploaded_image')) {
            return;
        }

        foreach ($tmpFilenames as $filename) {
            $path = $this->persistTemporaryUpload(
                TemporaryUploadedFile::createFromLivewire($filename)
            );

            if ($path !== null) {
                $this->commitGalleryUpload($path);

                return;
            }
        }
    }

    public function appendUploadedGalleryImage(mixed $state): void
    {
        $path = $this->resolveUploadedImagePath($state);
        if ($path === null) {
            return;
        }

        $this->commitGalleryUpload($path);
    }

    private function commitGalleryUpload(string $path): void
    {
        $url = Storage::disk('public')->url($path);
        $gallery = (array) ($this->data['gallery_images'] ?? []);

        if (! in_array($url, $gallery, true)) {
            array_unshift($gallery, $url);
        }

        $this->data['selected_image_url'] = $url;
        $this->data['gallery_images'] = $gallery;
        $this->data['uploaded_image'] = null;

        $this->getRecord()->update([
            'selected_image_url' => $url,
            'gallery_images' => $gallery,
        ]);

        Notification::make()->title('Kapak görseli yüklendi')->success()->send();
    }

    private function resolveUploadedImagePath(mixed $state): ?string
    {
        if ($state === null || $state === '' || $state === []) {
            return null;
        }

        if ($state instanceof TemporaryUploadedFile) {
            return $this->persistTemporaryUpload($state);
        }

        if (is_string($state)) {
            if (str_starts_with($state, 'livewire-file:')) {
                $temp = TemporaryUploadedFile::createFromLivewire(
                    substr($state, strlen('livewire-file:'))
                );

                return $this->persistTemporaryUpload($temp);
            }

            if (Storage::disk('public')->exists($state)) {
                return $state;
            }

            return null;
        }

        if (is_array($state)) {
            $normalized = TemporaryUploadedFile::canUnserialize($state)
                ? TemporaryUploadedFile::unserializeFromLivewireRequest($state)
                : $state;

            if ($normalized instanceof TemporaryUploadedFile) {
                return $this->persistTemporaryUpload($normalized);
            }

            foreach ($normalized as $item) {
                $path = $this->resolveUploadedImagePath($item);
                if ($path !== null) {
                    return $path;
                }
            }
        }

        return null;
    }

    private function persistTemporaryUpload(TemporaryUploadedFile $file): ?string
    {
        if (! $file->exists()) {
            return null;
        }

        $articleId = $this->getRecord()->id;
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid().'.'.$extension;
        $directory = "articles/uploads/{$articleId}";

        Storage::disk('public')->makeDirectory($directory);
        $file->storeAs($directory, $filename, ['disk' => 'public']);

        return "{$directory}/{$filename}";
    }

    public function rewriteTitleWithAi(): void
    {
        $this->internalSave();

        try {
            $title = app(AiService::class)->rewriteTitle((string) ($this->data['title'] ?? ''));
            $this->data['title'] = $title;
            $this->getRecord()->update(['title' => $title]);

            Notification::make()->title('Başlık özgünleştirildi')->success()->send();
        } catch (\Throwable $e) {
            app(ActivityLogger::class)->log('AI başlık hatası: '.$e->getMessage(), 'error', $this->getRecord());
            Notification::make()->title('AI hatası')->body($e->getMessage())->danger()->send();
        }
    }

    public function rewriteSummaryWithAi(?string $currentContent = null): void
    {
        try {
            $record = $this->getRecord();
            $summaryInput = $currentContent ?? ($this->data['summary'] ?? '');

            $summary = app(AiService::class)->rewriteSummary(
                (string) ($this->data['title'] ?? ''),
                $summaryInput,
                trim((string) ($this->data['source_name'] ?? $record->source_name ?? '')) ?: null,
                trim((string) ($this->data['source_url'] ?? $record->source_url ?? '')) ?: null,
                trim((string) ($record->link ?? '')) ?: null,
            );

            $this->data['summary'] = $summary;

            Notification::make()->title('Özet özgünleştirildi')->success()->send();
        } catch (\Throwable $e) {
            app(ActivityLogger::class)->log('AI özet hatası: '.$e->getMessage(), 'error', $this->getRecord());
            Notification::make()->title('AI hatası')->body($e->getMessage())->danger()->send();
        }
    }

    public function generateImage(): void
    {
        $this->persistImageGenerationFields();

        try {
            app(ArticleImageRegenerator::class)->regenerate($this->getRecord()->fresh());
            Notification::make()->title('Görsel üretildi')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Görsel üretilemedi')->body($e->getMessage())->danger()->send();
        }
    }

    private function persistImageGenerationFields(): void
    {
        $this->getRecord()->update([
            'title' => (string) ($this->data['title'] ?? ''),
            'selected_image_url' => $this->data['selected_image_url'] ?? null,
            'image_template_id' => $this->data['image_template_id'] ?? null,
        ]);
    }

    public function approveArticle(): void
    {
        $record = $this->getRecord()->fresh();

        if (! $record->generated_image_path) {
            Notification::make()
                ->title('Onay için görsel gerekli')
                ->body('Lütfen önce görsel üretin.')
                ->warning()
                ->send();

            return;
        }

        $this->internalSave();

        $record->update([
            'approved_at' => now(),
            'status' => ArticleStatus::Approved,
            'edited_at' => now(),
        ]);

        Notification::make()->title('Haber onaylandı ve arşive eklendi')->success()->send();
    }

    public function getPreviewUrl(): ?string
    {
        $path = $this->getRecord()->fresh()->generated_image_path;

        if (! $path) {
            return null;
        }

        $fullPath = storage_path('app/public/'.$path);
        $version = file_exists($fullPath) ? filemtime($fullPath) : time();

        return asset('storage/'.$path).'?v='.$version;
    }

    public function downloadGeneratedImage(): ?BinaryFileResponse
    {
        $path = $this->getRecord()->fresh()->generated_image_path;
        if (! $path) {
            Notification::make()->title('Henüz görsel üretilmedi')->warning()->send();

            return null;
        }

        $fullPath = Storage::disk('public')->path($path);
        if (! is_file($fullPath)) {
            Notification::make()->title('Görsel dosyası bulunamadı')->danger()->send();

            return null;
        }

        return response()->download(
            $fullPath,
            $this->buildImageDownloadFilename('haber', $path),
        );
    }

    public function downloadGalleryImage(string $url): ?BinaryFileResponse
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $localPath = app(ImageCacheService::class)->downloadToLocal(
            $url,
            $this->getRecord()->effective_source_url,
        );

        if ($localPath === null) {
            Notification::make()->title('Kapak görseli indirilemedi')->danger()->send();

            return null;
        }

        return response()->download(
            $localPath,
            $this->buildImageDownloadFilename('kapak', $localPath),
        );
    }

    private function buildImageDownloadFilename(string $prefix, string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        $slug = Str::slug(Str::limit((string) ($this->getRecord()->title ?? 'gorsel'), 40, ''));

        if ($slug === '') {
            $slug = 'gorsel';
        }

        return "{$prefix}-{$this->getRecord()->id}-{$slug}.{$extension}";
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->isSpecialContent()) {
            $actions[] = Action::make('refreshData')
                ->label('Veriyi Yenile')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Güncel veriyi çek')
                ->modalDescription('Kaynak API\'den veri tekrar çekilecek; özet ve payload güncellenecek. Mevcut görsel sıfırlanır.')
                ->action(fn () => $this->refreshSpecialContent());
        } else {
            $actions[] = Action::make('openSource')
                ->label('Orijinal haberi aç')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (): string => $this->getRecord()->effective_source_url ?? '#')
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->getRecord()->effective_source_url))
                ->color('gray');
        }

        $actions[] = $this->getSaveFormAction();
        $actions[] = DeleteAction::make()
            ->iconButton()
            ->label('')
            ->tooltip('Sil');

        return $actions;
    }

    public function refreshSpecialContent(): void
    {
        $record = $this->getRecord();
        $factory = app(SpecialContentFactory::class);

        try {
            $article = match ($record->type) {
                ArticleType::Gold => $factory->syncGold(auth()->user()),
                ArticleType::Weather => $factory->syncWeather(auth()->user()),
                default => null,
            };

            if ($article === null) {
                Notification::make()->title('Veri yenilenemedi')->danger()->send();

                return;
            }

            $this->record = $article;
            $this->fillForm();
            Notification::make()->title('Veri güncellendi')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Veri yenilenemedi')->body($e->getMessage())->danger()->send();
        }
    }

    protected function isSpecialContent(): bool
    {
        return $this->getRecord()->type?->isSpecial() ?? false;
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(fn (): string => $this->getSaveActionLabel())
            ->color(fn (): string => $this->getRecord()->approved_at === null ? 'warning' : 'primary')
            ->action('save')
            ->keyBindings(['mod+s']);
    }

    protected function getSaveActionLabel(): string
    {
        return $this->getRecord()->approved_at === null
            ? 'Onayla'
            : 'Değişiklikleri Kaydet';
    }

    protected function canApproveFromSidebar(): bool
    {
        $record = $this->getRecord()->fresh();

        return $record->status === ArticleStatus::Pending
            && $record->approved_at === null
            && $record->edited_at !== null;
    }

    protected function beforeSave(): void
    {
        if ($this->isInternalSave) {
            return;
        }

        $record = $this->getRecord()->fresh();

        $this->isFirstApproval = $record->approved_at === null;

        if ($this->isFirstApproval && ! $record->generated_image_path) {
            Notification::make()
                ->title('Onay için görsel gerekli')
                ->body('Lütfen önce kapak görseli seçip «Görseli Üret» butonuna tıklayın.')
                ->warning()
                ->send();

            throw new Halt;
        }
    }

    protected function afterSave(): void
    {
        if ($this->isInternalSave) {
            return;
        }

        $updates = ['edited_at' => now()];

        if ($this->isFirstApproval) {
            $updates['approved_at'] = now();
            $updates['status'] = ArticleStatus::Approved;
        }

        $this->getRecord()->update($updates);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->isFirstApproval
            ? 'Haber onaylandı ve arşive eklendi'
            : 'Değişiklikler kaydedildi';
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    /** @return list<Action> */
    protected function imageProductionActions(): array
    {
        return [
            Action::make('generateImage')
                ->label('Görseli Üret')
                ->icon(Heroicon::OutlinedPhoto)
                ->button()
                ->color('primary')
                ->size(Size::Large)
                ->action(fn () => $this->generateImage()),
            Action::make('downloadImage')
                ->label('Anlık İndir')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->button()
                ->color('success')
                ->size(Size::Large)
                ->visible(fn (): bool => (bool) $this->getRecord()->fresh()->generated_image_path)
                ->action(fn (): ?BinaryFileResponse => $this->downloadGeneratedImage()),
        ];
    }

    /** @return array<string, mixed> */
    protected function previewViewData(ArticleGalleryService $galleryService): array
    {
        return [
            'generatedUrl' => $this->getPreviewUrl(),
            'coverUrl' => $galleryService->toDisplayUrl((string) ($this->data['selected_image_url'] ?? '')) ?: null,
        ];
    }

    /** @return list<View> */
    protected function galleryPoolComponents(ArticleGalleryService $galleryService): array
    {
        $gallery = $galleryService->normalizeForDisplay((array) ($this->data['gallery_images'] ?? []));
        $selected = $galleryService->toDisplayUrl((string) ($this->data['selected_image_url'] ?? '')) ?: null;
        $gridKey = md5(json_encode([$gallery, $selected]));

        if ($gallery === []) {
            return [
                View::make('filament.articles.images-empty')
                    ->key("pool-empty-{$gridKey}")
                    ->columnSpanFull(),
            ];
        }

        $components = [];

        foreach ($gallery as $url) {
            $components[] = View::make('filament.articles.gallery-pool-card')
                ->key('pool-'.md5($url)."-{$gridKey}")
                ->viewData([
                    'url' => $url,
                    'isSelected' => $selected === $url,
                ]);
        }

        return $components;
    }

    protected function summaryField(): RichEditor
    {
        $field = RichEditor::make('summary')
            ->label('Özet')
            ->columnSpanFull();

        if ($this->isSpecialContent()) {
            return $field
                ->disabled()
                ->dehydrated()
                ->toolbarButtons([]);
        }

        return $field
            ->tools([
                RichEditorTool::make('rewriteSummaryAi')
                    ->label('AI Özgünleştir')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->action(arguments: '{ content: $getEditor().getHTML() }'),
                RichEditorTool::make('copySummary')
                    ->label('Kopyala')
                    ->icon(Heroicon::OutlinedClipboardDocument)
                    ->jsHandler('window.navigator.clipboard.writeText($getEditor().getText()); $tooltip(\'Özet kopyalandı\', { theme: $store.theme, timeout: 2000 })'),
            ])
            ->registerActions([
                Action::make('rewriteSummaryAi')
                    ->label('AI ile Özgünleştir')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Özeti özgünleştir')
                    ->modalDescription('Editörde o an görünen özet OpenAI ile yeniden yazılacak.')
                    ->action(function (array $arguments): void {
                        $this->rewriteSummaryWithAi($arguments['content'] ?? null);
                    }),
            ])
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'link'],
                ['bulletList', 'orderedList', 'blockquote'],
                ['rewriteSummaryAi', 'copySummary', 'undo', 'redo'],
            ]);
    }

    public function form(Schema $schema): Schema
    {
        $record = $this->getRecord();
        $galleryService = app(ArticleGalleryService::class);
        $isSpecial = $this->isSpecialContent();
        $contentLabel = match ($record->type) {
            ArticleType::Gold => 'Altın Fiyatları',
            ArticleType::Weather => 'Hava Durumu',
            default => 'Haber İçeriği',
        };

        $mainContentSchema = [
            TextInput::make('title')
                ->label('Başlık')
                ->required()
                ->maxLength(500)
                ->live(debounce: 400)
                ->columnSpanFull()
                ->suffixActions($isSpecial ? [] : [
                    Action::make('searchGoogle')
                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                        ->label('Google')
                        ->tooltip('Google\'da ara')
                        ->color('gray')
                        ->url(fn (): string => 'https://www.google.com/search?q='.urlencode((string) ($this->data['title'] ?? '')))
                        ->openUrlInNewTab(),
                    CopyAction::make()
                        ->label('Kopyala')
                        ->tooltip('Başlığı kopyala')
                        ->copyMessage('Başlık kopyalandı'),
                    Action::make('rewriteTitleAi')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->label('AI')
                        ->tooltip('Başlığı AI ile özgünleştir')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Başlığı özgünleştir')
                        ->modalDescription('Yalnızca başlık alanı OpenAI ile yeniden yazılacak.')
                        ->action(fn () => $this->rewriteTitleWithAi()),
                ]),
            $this->summaryField(),
        ];

        if ($isSpecial) {
            $mainContentSchema[] = View::make('filament.articles.special-data-panel')
                ->viewData(fn (): array => [
                    'payload' => $record->fresh()->payload ?? [],
                    'type' => $record->type?->value,
                    'dataFetchedAt' => $record->fresh()->data_fetched_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                ])
                ->columnSpanFull();
        }

        return $schema
            ->columns(1)
            ->components([
                View::make('filament.articles.article-source-bar')
                    ->viewData(fn () => [
                        'sourceName' => $this->data['source_name'] ?? '—',
                        'status' => $record->fresh()->status?->label(),
                    ]),

                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Grid::make(1)
                            ->columnSpan(['default' => 1, 'lg' => 2])
                            ->schema([
                                Section::make($contentLabel)
                                    ->description($isSpecial
                                        ? 'Başlık düzenlenebilir; özet ve veri tablosu kaynakla senkron kalır'
                                        : 'Başlık ve özet — onay sonrası da düzenlenebilir')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->schema($mainContentSchema),

                                Section::make('Kaynaklar')
                                    ->description('RSS ve yayıncı linkleri')
                                    ->icon(Heroicon::OutlinedRss)
                                    ->visible(! $isSpecial)
                                    ->schema([
                                        Tabs::make('KaynakDetay')
                                            ->tabs([
                                                Tab::make('Orijinal metin')
                                                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                                                    ->schema([
                                                        TextInput::make('original_title')
                                                            ->label('Orijinal Başlık')
                                                            ->disabled()
                                                            ->columnSpanFull(),
                                                        RichEditor::make('original_content')
                                                            ->label('Orijinal Metin')
                                                            ->disabled()
                                                            ->columnSpanFull()
                                                            ->toolbarButtons([]),
                                                    ]),
                                                Tab::make('Linkler')
                                                    ->icon(Heroicon::OutlinedLink)
                                                    ->schema([
                                                        TextInput::make('source_url')
                                                            ->label('Yayıncı URL')
                                                            ->disabled()
                                                            ->columnSpanFull(),
                                                        TextInput::make('link')
                                                            ->label('RSS / Google News Link')
                                                            ->disabled()
                                                            ->columnSpanFull(),
                                                    ]),
                                            ]),
                                    ]),
                            ]),

                        Grid::make(1)
                            ->columnSpan(['default' => 1, 'lg' => 1])
                            ->schema([
                                Section::make('Aktif Kapak ve Üretim')
                                    ->icon(Heroicon::OutlinedPhoto)
                                    ->schema([
                                        View::make('filament.articles.article-preview')
                                            ->key(fn (): string => 'preview-'.md5(
                                                (string) $this->getPreviewUrl()
                                                .'-'.($this->data['selected_image_url'] ?? '')
                                            ))
                                            ->viewData(fn (): array => $this->previewViewData($galleryService))
                                            ->columnSpanFull(),
                                        Select::make('image_template_id')
                                            ->label('Görsel şablonu')
                                            ->options(fn () => app(TemplateService::class)->optionsForSelect($record->type))
                                            ->placeholder('Varsayılan şablon')
                                            ->native(false)
                                            ->searchable()
                                            ->live()
                                            ->columnSpanFull(),
                                        Actions::make($this->imageProductionActions())
                                            ->columnSpanFull()
                                            ->fullWidth()
                                            ->extraAttributes(['class' => 'haber-sidebar-actions']),
                                    ]),

                                Section::make('Görsel Havuzu')
                                    ->description('Görsele tıkla: kapak · X: gizle · İndir: dosya')
                                    ->icon(Heroicon::OutlinedRectangleStack)
                                    ->visible(! $isSpecial)
                                    ->schema([
                                        FileUpload::make('uploaded_image')
                                            ->label('Bilgisayardan yükle')
                                            ->image()
                                            ->disk('public')
                                            ->directory(fn () => 'articles/uploads/'.$record->id)
                                            ->visibility('public')
                                            ->maxSize(8192)
                                            ->imagePreviewHeight('80')
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                        Grid::make(2)
                                            ->schema(fn (): array => $this->galleryPoolComponents($galleryService))
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['uploaded_image']);

        if ($this->isSpecialContent()) {
            unset($data['summary']);

            return $data;
        }

        if (blank($this->getRecord()->original_title)) {
            $data['original_title'] = $data['title'] ?? '';
            $summaryText = trim(strip_tags((string) ($data['summary'] ?? '')));
            $data['original_content'] = trim(($data['title'] ?? '').($summaryText !== '' ? "\n\n".$summaryText : ''));
        }

        if (blank($this->getRecord()->link) && filled($data['source_url'] ?? null)) {
            $data['link'] = $data['source_url'];
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['gallery_images'] = app(ArticleGalleryService::class)
            ->normalizeForDisplay((array) ($data['gallery_images'] ?? []));

        if (filled($data['summary'] ?? null)) {
            $data['summary'] = app(AiService::class)->plainTextToHtml((string) $data['summary']);
        }

        if (filled($data['original_content'] ?? null)) {
            $data['original_content'] = app(AiService::class)->plainTextToHtml((string) $data['original_content']);
        }

        return $data;
    }
}
