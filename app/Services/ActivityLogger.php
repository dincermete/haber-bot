<?php

namespace App\Services;

use App\Models\Article;
use App\Models\SendLog;

class ActivityLogger
{
    public function log(string $message, string $level = 'info', ?Article $article = null, array $context = []): SendLog
    {
        return SendLog::create([
            'article_id' => $article?->id,
            'level' => $level,
            'message' => $message,
            'context' => $context ?: null,
        ]);
    }
}
