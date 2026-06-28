<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Processing = 'processing';
    case Pending = 'pending';
    case Approved = 'approved';
    case Failed = 'failed';
    case Filtered = 'filtered';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'İşleniyor',
            self::Pending => 'Onay Bekliyor',
            self::Approved => 'Onaylandı',
            self::Failed => 'Başarısız',
            self::Filtered => 'Filtrelendi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Processing => 'info',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Failed => 'danger',
            self::Filtered => 'gray',
        };
    }
}
