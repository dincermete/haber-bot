<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $fillable = [
        'type',
        'feed_id',
        'created_by_user_id',
        'article_uid',
        'original_title',
        'original_content',
        'title',
        'summary',
        'payload',
        'data_fetched_at',
        'last_successful_payload',
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
            'type' => ArticleType::class,
            'status' => ArticleStatus::class,
            'payload' => 'array',
            'last_successful_payload' => 'array',
            'data_fetched_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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

    public function scopeOfType(Builder $query, ArticleType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeNews(Builder $query): Builder
    {
        return $query->where('type', ArticleType::News);
    }

    public function isSpecialContent(): bool
    {
        return $this->type?->isSpecial() ?? false;
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if (! $this->generated_image_path) {
            return null;
        }

        return asset('storage/'.$this->generated_image_path);
    }
}
