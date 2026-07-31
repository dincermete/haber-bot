<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\SpecialContentFactory;
use Illuminate\Console\Command;

class SyncWeather extends Command
{
    protected $signature = 'weather:sync';

    protected $description = 'Open-Meteo ile 11 ilçe hava durumunu çekip günlük pending kaydı oluşturur veya günceller';

    public function handle(SpecialContentFactory $factory, ActivityLogger $logger): int
    {
        try {
            $article = $factory->syncWeather();

            if ($article === null) {
                $this->warn('Hava durumu kaydı oluşturulamadı.');

                return self::FAILURE;
            }

            $stale = ($article->payload['stale'] ?? false) ? ' (önceki veri)' : '';
            $this->info("Hava durumu senkronize edildi (Article #{$article->id}){$stale}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $logger->log('weather:sync hatası: '.$e->getMessage(), 'error');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
