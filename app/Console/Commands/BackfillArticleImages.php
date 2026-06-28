<?php

namespace App\Console\Commands;

use App\Jobs\RegenerateArticleImageJob;
use App\Models\Article;
use Illuminate\Console\Command;

class BackfillArticleImages extends Command
{
    protected $signature = 'articles:backfill-images {--limit=100 : Maksimum işlenecek haber sayısı} {--force : Onay olmadan çalıştır}';

    protected $description = 'Görseli olmayan haberler için görsel üretimini kuyruğa alır (opt-in)';

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
            RegenerateArticleImageJob::dispatch($article);
        }

        $this->info("{$articles->count()} haber için görsel üretimi kuyruğa alındı.");

        return self::SUCCESS;
    }
}
