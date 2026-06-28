<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ImageTemplate;
use App\Models\Setting;
use Illuminate\Support\Collection;

class TemplateService
{
    public function getAll(): Collection
    {
        return ImageTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getDefault(): ?ImageTemplate
    {
        return ImageTemplate::query()
            ->where('is_default', true)
            ->orderBy('sort_order')
            ->first()
            ?? ImageTemplate::query()->orderBy('sort_order')->first();
    }

    public function resolveForArticle(Article $article, ?int $templateId = null): ?ImageTemplate
    {
        if ($templateId) {
            $template = ImageTemplate::query()->find($templateId);
            if ($template) {
                return $template;
            }
        }

        if ($article->image_template_id) {
            $template = $article->imageTemplate;
            if ($template) {
                return $template;
            }
        }

        return $this->getDefault();
    }

    public function getSettings(ImageTemplate $template): array
    {
        $defaults = ImageTemplate::defaultSettings();
        $stored = $template->settings ?? [];

        return array_merge($defaults, $stored);
    }

    public function optionsForSelect(): array
    {
        return $this->getAll()
            ->mapWithKeys(fn (ImageTemplate $t) => [$t->id => $t->name])
            ->all();
    }

    public function legacyTemplatePath(): string
    {
        return (string) Setting::get('image_design_template', 'sablon.png');
    }
}
