<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        if ($this->app->environment('local')) {
            $rootUrl = request()->getSchemeAndHttpHost();

            if ($rootUrl) {
                URL::forceRootUrl($rootUrl);
            }
        }
    }
}
