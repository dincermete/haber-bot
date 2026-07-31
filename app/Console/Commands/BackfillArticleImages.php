<?php

namespace App\Console\Commands;

use App\Services\ArticleImageRegenerator;
use App\Models\Article;
use Illuminate\Console\Command;

class BackfillArticleImages extends Command
{
    protected $signature = 'articles:backfill-images {--limit=100 : Maksimum işlenecek haber sayısı} {--force : Onay olmadan çalıştır}';

    protected $description = 'Görseli olmayan haberler için görsel üretir (opt-in, senkron)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('Otomatik görsel üretimi devre dışı. Kullanmak için --force ekleyin.');

            return self::SUCCESS;
        }
        $limit = max(1, (int) $this->option('limit'));

        $articles = Article::query()
            ->where(function ($query) {
                $query->whereNull('generated_image_path')
                    ->orWhere('generated_image_path', '');
            })
            ->latest()
            ->limit($limit)
            ->get();

        if ($articles->isEmpty()) {
            $this->info('Görseli eksik haber bulunamadı.');

            return self::SUCCESS;
        }

        foreach ($articles as $article) {
            try {
                app(ArticleImageRegenerator::class)->regenerate($article);
                $this->line("Üretildi: {$article->title}");
            } catch (\Throwable $e) {
                $this->error("Başarısız ({$article->id}): {$e->getMessage()}");
            }
        }

        $this->info("{$articles->count()} haber işlendi.");

        return self::SUCCESS;
    }
}
