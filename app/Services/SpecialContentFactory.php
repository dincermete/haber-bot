<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Exceptions\GoldPriceFetchException;
use App\Exceptions\WeatherFetchException;
use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SpecialContentFactory
{
    public function __construct(
        private readonly EkeoGoldPriceService $goldService,
        private readonly OpenMeteoWeatherService $weatherService,
        private readonly ActivityLogger $logger,
    ) {}

    public function syncGold(?User $user = null): Article
    {
        try {
            $payload = $this->goldService->fetch();
        } catch (GoldPriceFetchException $e) {
            $this->logger->log('Altın fiyatı çekilemedi: '.$e->getMessage(), 'error');

            throw $e;
        }

        return $this->upsert(ArticleType::Gold, $payload, $user, function (array $p) {
            return [
                'title' => $this->goldService->buildTitle($p),
                'summary' => $this->goldService->buildSummaryHtml($p),
                'source_name' => 'EKEO',
                'source_url' => 'https://fiyat.ekeo.org.tr/',
            ];
        });
    }

    public function syncWeather(?User $user = null): ?Article
    {
        try {
            $payload = $this->weatherService->fetch();
            Setting::set('weather_last_successful_payload', json_encode($payload));
        } catch (WeatherFetchException $e) {
            $fallback = $this->resolveWeatherFallback();

            if ($fallback === null) {
                $this->logger->log('Hava durumu çekilemedi (fallback yok): '.$e->getMessage(), 'error');

                throw $e;
            }

            $fetchedAt = $fallback['fetched_at'] ?? null;
            $displayDate = $fetchedAt
                ? \Carbon\Carbon::parse($fetchedAt)->timezone('Europe/Istanbul')->format('d.m.Y H:i')
                : 'bilinmiyor';

            $payload = array_merge($fallback, [
                'stale' => true,
                'stale_note' => "API geçici hata — son başarılı veri: {$displayDate}",
            ]);

            $this->logger->log('Hava durumu API hatası, önceki veri kullanıldı: '.$e->getMessage(), 'warning');
        }

        return $this->upsert(ArticleType::Weather, $payload, $user, function (array $p) {
            return [
                'title' => $this->weatherService->buildTitle($p),
                'summary' => $this->weatherService->buildSummaryHtml($p),
                'source_name' => 'Open-Meteo',
                'source_url' => 'https://open-meteo.com/',
            ];
        }, storeLastSuccessful: true);
    }

    /**
     * @param  callable(array): array{title: string, summary: string, source_name: string, source_url: string}  $formatter
     */
    private function upsert(
        ArticleType $type,
        array $payload,
        ?User $user,
        callable $formatter,
        bool $storeLastSuccessful = false,
    ): Article {
        $uid = self::deterministicUid($type);
        $formatted = $formatter($payload);
        $plainSummary = trim(strip_tags($formatted['summary']));

        return DB::transaction(function () use ($type, $payload, $user, $uid, $formatted, $plainSummary, $storeLastSuccessful) {
            $existing = Article::query()->where('article_uid', $uid)->first();

            $attributes = [
                'type' => $type,
                'feed_id' => null,
                'article_uid' => $uid,
                'original_title' => $formatted['title'],
                'original_content' => $plainSummary,
                'title' => $formatted['title'],
                'summary' => $formatted['summary'],
                'link' => '',
                'source_name' => $formatted['source_name'],
                'source_url' => $formatted['source_url'],
                'payload' => $payload,
                'data_fetched_at' => now(),
                'created_by_user_id' => $user?->id,
                'generated_image_path' => null,
            ];

            if ($storeLastSuccessful && ! ($payload['stale'] ?? false)) {
                $attributes['last_successful_payload'] = $payload;
            }

            if ($existing) {
                if ($existing->status === ArticleStatus::Approved) {
                    return $existing;
                }

                $existing->update($attributes);
                $this->logger->log("{$type->label()} verisi güncellendi", 'info', $existing->fresh());

                return $existing->fresh();
            }

            $article = Article::create(array_merge($attributes, [
                'status' => ArticleStatus::Pending,
            ]));

            $this->logger->log("{$type->label()} kaydı oluşturuldu", 'info', $article);

            return $article;
        });
    }

    public static function deterministicUid(ArticleType $type): string
    {
        $date = now()->timezone('Europe/Istanbul')->toDateString();

        return hash('sha256', $type->value.':'.$date);
    }

    /**
     * @return ?array<string, mixed>
     */
    private function resolveWeatherFallback(): ?array
    {
        $fromSetting = Setting::get('weather_last_successful_payload');

        if (is_string($fromSetting) && $fromSetting !== '') {
            $decoded = json_decode($fromSetting, true);
            if (is_array($decoded) && isset($decoded['districts'])) {
                return $decoded;
            }
        }

        $article = Article::query()
            ->ofType(ArticleType::Weather)
            ->whereNotNull('last_successful_payload')
            ->orderByDesc('data_fetched_at')
            ->first();

        if ($article?->last_successful_payload) {
            return $article->last_successful_payload;
        }

        $approved = Article::query()
            ->ofType(ArticleType::Weather)
            ->approved()
            ->whereNotNull('payload')
            ->orderByDesc('data_fetched_at')
            ->first();

        return $approved?->payload;
    }
}
