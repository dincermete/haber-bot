<?php

namespace App\Enums;

enum ArticleType: string
{
    case News = 'news';
    case Gold = 'gold';
    case Weather = 'weather';

    public function label(): string
    {
        return match ($this) {
            self::News => 'Haber',
            self::Gold => 'Altın',
            self::Weather => 'Hava Durumu',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::News => 'gray',
            self::Gold => 'warning',
            self::Weather => 'info',
        };
    }

    public function isSpecial(): bool
    {
        return $this !== self::News;
    }
}
