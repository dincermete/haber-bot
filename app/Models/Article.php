<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $fillable = [
        'feed_id',
        'article_uid',
        'original_title',
        'original_content',
        'title',
        'summary',
        'link',
        'source_name',
        'source_url',
        'gallery_images',
        'selected_image_url',
        'image_template_id',
        'source_image_url',
        'generated_image_path',
        'status',
        'error_message',
        'approved_at',
        'edited_at',
        'telegram_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'gallery_images' => 'array',
            'approved_at' => 'datetime',
            'edited_at' => 'datetime',
            'telegram_sent_at' => 'datetime',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function imageTemplate(): BelongsTo
    {
        return $this->belongsTo(ImageTemplate::class);
    }

    public function getEffectiveSourceUrlAttribute(): string
    {
        return $this->source_url ?: $this->link;
    }

    public function getEffectiveImageUrlAttribute(): ?string
    {
        return $this->selected_image_url ?: $this->source_image_url;
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(SendLog::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Pending);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Processing);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Approved);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Failed);
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if (! $this->generated_image_path) {
            return null;
        }

        return asset('storage/'.$this->generated_image_path);
    }
}
