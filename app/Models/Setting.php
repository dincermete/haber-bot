<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public $timestamps = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('app_settings', 60, fn () => self::pluck('value', 'key')->all());

        return $settings[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget('app_settings');
    }

    /** @return list<string> */
    public static function aiPromptKeys(): array
    {
        return array_keys(self::aiPromptDefaults());
    }

    public static function aiPromptDefaults(): array
    {
        return [
            'ai_prompt_title' => 'Sen profesyonel bir Türkçe haber editörüsün. Görevin, verilen haber başlığını clickbait (tık tuzağı) ögelerinden arındırarak, özgün, dikkat çekici ve akıcı bir Türkçe ile yeniden yazmaktır. Yanıtı kesinlikle şu JSON şemasında döndür, asla ekstra metin veya markdown işareti ekleme: {"title": "...."}',
            'ai_prompt_summary' => 'Sen profesyonel bir Türkçe haber editörüsün. Görevin, verilen haber özetini clickbait ögelerinden arındırıp, özgün ve akıcı bir Türkçe ile haber bültenine uygun olarak yeniden yazmaktır.

summary alanına YALNIZCA haber metnini yaz. Kaynak satırı veya hashtag ekleme.

Yanıtı kesinlikle şu JSON şemasında döndür; summary düz metin olsun, HTML kullanma: {"summary": "..."}',
            'ai_prompt_generate' => 'Sen bir Türkçe haber editörüsün. Verilen başlık ve özeti clickbait olmadan, özgün ve akıcı Türkçe ile yeniden yaz. Yanıtı kesinlikle geçerli bir JSON objesi formatında ve şu şemaya birebir uygun döndür: {"title": "...", "summary": "..."}',
        ];
    }

    public static function ensureAiPrompts(): void
    {
        $defaults = self::aiPromptDefaults();

        foreach ($defaults as $key => $default) {
            if (self::query()->where('key', $key)->exists()) {
                continue;
            }

            $value = $default;

            if ($key === 'ai_prompt_generate') {
                $legacy = (string) self::query()->where('key', 'ai_system_prompt')->value('value');
                if ($legacy !== '') {
                    $value = $legacy.' Yanıtı kesinlikle geçerli bir JSON objesi formatında ve şu şemaya birebir uygun döndür: {"title": "...", "summary": "..."}';
                }
            }

            self::set($key, $value);
        }
    }

    public static function defaults(): array
    {
        return array_merge([
            'scan_interval_minutes' => '5',
            'image_canvas_width' => '1080',
            'image_canvas_height' => '1080',
            'text_x' => '60',
            'text_y' => '720',
            'image_title_font_size' => '48',
            'image_padding' => '60',
            'image_title_wrap_width' => '40',
            'image_title_color' => '255,255,255',
            'image_default_bg_color' => '30,30,40',
            'image_design_template' => 'sablon.png',
            'ai_openai_api_key' => '',
            'ai_openai_model' => 'gpt-4o-mini',
            'telegram_bot_token' => '',
            'telegram_chat_id' => '',
            'telegram_enabled' => '0',
            'telegram_send_photo' => '1',
        ], self::aiPromptDefaults());
    }
}
