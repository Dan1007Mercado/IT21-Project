<?php

namespace App\Providers;

use App\Services\Security\IntsecSettings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        try {
            if (Schema::hasTable('system_settings')) {
                IntsecSettings::refreshConfig();
            }
        } catch (Throwable) {
            // Settings are an optional runtime override. A temporarily
            // unavailable database must not prevent Artisan or the app from
            // booting with safe configuration defaults.
        }
    }
}
