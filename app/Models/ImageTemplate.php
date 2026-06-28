<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ImageTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'file_path',
        'is_default',
        'sort_order',
        'canvas_width',
        'canvas_height',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function getPreviewUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public static function defaultSettings(): array
    {
        return [
            'text_x' => (int) Setting::get('text_x', 60),
            'text_y' => (int) Setting::get('text_y', 720),
            'font_size' => (int) Setting::get('image_title_font_size', 48),
            'padding' => (int) Setting::get('image_padding', 60),
            'wrap_width' => (int) Setting::get('image_title_wrap_width', 40),
            'title_color' => (string) Setting::get('image_title_color', '255,255,255'),
            'default_bg_color' => (string) Setting::get('image_default_bg_color', '30,30,40'),
        ];
    }
}
