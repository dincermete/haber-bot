<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\SpecialContentFactory;
use Illuminate\Console\Command;

class SyncGoldPrices extends Command
{
    protected $signature = 'gold:sync';

    protected $description = 'EKEO altın fiyatlarını çekip günlük pending kaydı oluşturur veya günceller';

    public function handle(SpecialContentFactory $factory, ActivityLogger $logger): int
    {
        try {
            $article = $factory->syncGold();
            $this->info("Altın fiyatları senkronize edildi (Article #{$article->id}).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $logger->log('gold:sync hatası: '.$e->getMessage(), 'error');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
