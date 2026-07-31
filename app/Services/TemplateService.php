<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ImageTemplate;
use App\Enums\ArticleType;
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

        $typeSlug = match ($article->type) {
            ArticleType::Gold => 'altin-fiyatlari',
            ArticleType::Weather => 'hava-durumu',
            default => null,
        };

        if ($typeSlug) {
            $typed = ImageTemplate::query()->where('slug', $typeSlug)->first();
            if ($typed) {
                return $typed;
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

    public function optionsForSelect(?ArticleType $type = null): array
    {
        $query = $this->getAll();

        if ($type === ArticleType::Gold) {
            $query = $query->filter(fn (ImageTemplate $t) => $t->slug === 'altin-fiyatlari' || $t->is_default);
        } elseif ($type === ArticleType::Weather) {
            $query = $query->filter(fn (ImageTemplate $t) => $t->slug === 'hava-durumu' || $t->is_default);
        } elseif ($type === ArticleType::News || $type === null) {
            $query = $query->reject(fn (ImageTemplate $t) => in_array($t->slug, ['altin-fiyatlari', 'hava-durumu'], true));
        }

        return $query
            ->mapWithKeys(fn (ImageTemplate $t) => [$t->id => $t->name])
            ->all();
    }

    public function legacyTemplatePath(): string
    {
        return (string) Setting::get('image_design_template', 'sablon.png');
    }
}
