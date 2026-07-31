<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rss:check')->everyFiveMinutes();

Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-queue.log'));

Schedule::command('gold:sync')
    ->timezone('Europe/Istanbul')
    ->dailyAt('09:00');

Schedule::command('gold:sync')
    ->timezone('Europe/Istanbul')
    ->dailyAt('12:00');

Schedule::command('gold:sync')
    ->timezone('Europe/Istanbul')
    ->dailyAt('18:15');

Schedule::command('weather:sync')
    ->timezone('Europe/Istanbul')
    ->dailyAt('06:00');

Schedule::command('weather:sync')
    ->timezone('Europe/Istanbul')
    ->dailyAt('12:00');

Schedule::command('weather:sync')
    ->timezone('Europe/Istanbul')
    ->dailyAt('18:00');
