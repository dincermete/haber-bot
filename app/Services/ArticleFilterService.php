<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Filter;
use Illuminate\Support\Collection;
use Normalizer;

class ArticleFilterService
{
    public function shouldSend(Article $article, ?Collection $filters = null): bool
    {
        $filters = $filters ?? Filter::all();

        if ($filters->isEmpty()) {
            return true;
        }

        $grouped = $this->groupRules($filters);
        $content = trim($article->title.' '.$article->summary);

        if ($this->matchesGroup($content, $grouped['blacklist']['and'], 'and')
            || $this->matchesGroup($content, $grouped['blacklist']['or'], 'or')) {
            return false;
        }

        $hasWhitelist = ! empty($grouped['whitelist']['and']) || ! empty($grouped['whitelist']['or']);
        if (! $hasWhitelist) {
            return true;
        }

        return $this->matchesGroup($content, $grouped['whitelist']['and'], 'and')
            || $this->matchesGroup($content, $grouped['whitelist']['or'], 'or');
    }

    private function groupRules(Collection $filters): array
    {
        $grouped = [
            'whitelist' => ['and' => [], 'or' => []],
            'blacklist' => ['and' => [], 'or' => []],
        ];

        foreach ($filters as $filter) {
            $keywords = array_values(array_filter(array_map('trim', explode(',', $filter->keyword))));
            if ($keywords === []) {
                continue;
            }

            $listType = in_array($filter->list_type, ['whitelist', 'blacklist'], true)
                ? $filter->list_type : 'blacklist';
            $logicMode = in_array($filter->logic_mode, ['and', 'or'], true)
                ? $filter->logic_mode : 'or';

            $grouped[$listType][$logicMode][] = $keywords;
        }

        return $grouped;
    }

    private function matchesGroup(string $text, array $keywordGroups, string $logicMode): bool
    {
        if ($keywordGroups === []) {
            return false;
        }

        $normalizedText = $this->normalize($text);

        foreach ($keywordGroups as $keywords) {
            $normalizedKeywords = array_map(fn ($k) => $this->normalize($k), $keywords);

            if ($logicMode === 'and') {
                if (collect($normalizedKeywords)->every(fn ($k) => str_contains($normalizedText, $k))) {
                    return true;
                }
            } else {
                if (collect($normalizedKeywords)->contains(fn ($k) => str_contains($normalizedText, $k))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;
        }

        return mb_strtolower($text, 'UTF-8');
    }
}
