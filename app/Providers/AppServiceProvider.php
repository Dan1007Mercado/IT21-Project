<?php

namespace App\Providers;

use App\Models\AuthenticationLog;
use App\Models\BlockedIp;
use App\Models\Incident;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Observers\BroadcastSecurityState;
use App\Services\Security\IntsecSettings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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
        foreach ([RequestActivity::class, AuthenticationLog::class, SecurityEvent::class, SecurityAlert::class, Incident::class, BlockedIp::class] as $model) {
            $model::observe(BroadcastSecurityState::class);
        }

        /*
         * Cloudflare Tunnel terminates HTTPS at Cloudflare and forwards
         * the request to Laravel locally over HTTP.
         *
         * When APP_URL uses HTTPS, force Laravel-generated URLs
         * (including Vite assets) to use HTTPS as well.
         */
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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
