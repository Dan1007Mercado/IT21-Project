<?php

namespace App\Console\Commands;

use App\Events\SecurityStateChanged;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RealtimeCheck extends Command
{
    protected $signature = 'intsec:realtime-check {--broadcast : Dispatch a safe diagnostic event after the checks}';

    protected $description = 'Check INTSEC broadcasting, Reverb, channel routes, and queue prerequisites';

    public function handle(): int
    {
        $queueConnection = (string) config('intsec.realtime_queue_connection');
        $queueTable = (string) config("queue.connections.{$queueConnection}.table", 'jobs');

        $this->components->twoColumnDetail('Broadcast driver', (string) config('broadcasting.default'));
        $this->components->twoColumnDetail('Application queue driver', (string) config('queue.default'));
        $this->components->twoColumnDetail('Realtime queue connection', $queueConnection);
        $this->components->twoColumnDetail('Reverb public host', (string) config('broadcasting.connections.reverb.options.host'));
        $this->components->twoColumnDetail('Reverb public port', (string) config('broadcasting.connections.reverb.options.port'));
        $this->components->twoColumnDetail('Reverb public scheme', (string) config('broadcasting.connections.reverb.options.scheme'));
        $this->components->twoColumnDetail('Reverb bind address', (string) config('reverb.servers.reverb.host'));
        $this->components->twoColumnDetail('Reverb bind port', (string) config('reverb.servers.reverb.port'));
        $this->components->twoColumnDetail('Reverb app key configured', $this->configured(config('broadcasting.connections.reverb.key')));
        $this->components->twoColumnDetail('Reverb secret configured', $this->configured(config('broadcasting.connections.reverb.secret')));
        $broadcastRouteRegistered = collect(Route::getRoutes()->getRoutes())
            ->contains(fn ($route): bool => $route->uri() === 'broadcasting/auth');
        $this->components->twoColumnDetail('Broadcast auth route registered', $this->yesNo($broadcastRouteRegistered));

        if ($queueConnection === 'database') {
            try {
                $this->components->twoColumnDetail('Queue database table available', $this->yesNo(Schema::hasTable($queueTable)));
            } catch (Throwable $exception) {
                $this->components->twoColumnDetail('Queue database table available', 'ERROR: database unavailable');
            }
            $this->warn('Queued realtime delivery requires a supervised queue worker.');
        } else {
            $this->components->twoColumnDetail('Queue worker required for realtime', 'NO');
        }

        if ($this->option('broadcast')) {
            SecurityStateChanged::dispatch('diagnostic', 'checked', 'intsec', [
                'message' => 'Safe realtime diagnostic event',
            ]);
            $this->info('Diagnostic event dispatched. Confirm receipt in an authenticated administrator browser.');
        }

        return self::SUCCESS;
    }

    private function configured(mixed $value): string
    {
        return $this->yesNo(is_string($value) && trim($value) !== '');
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'YES' : 'NO';
    }
}
