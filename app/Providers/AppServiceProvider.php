<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\AuthenticationLog;
use App\Models\BlockedIp;
use App\Models\Incident;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use App\Models\SystemSetting;
use App\Observers\BroadcastSecurityState;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        foreach ([RequestActivity::class, AuthenticationLog::class, SecurityEvent::class, SecurityAlert::class, Incident::class, BlockedIp::class, AuditLog::class, SystemSetting::class] as $model) {
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

        // Runtime security settings are resolved by IntsecSettings at the
        // decision point. Do not contact the database during application
        // boot: health checks and static/error responses must remain usable
        // while the remote database is slow or temporarily unavailable.
    }
}
