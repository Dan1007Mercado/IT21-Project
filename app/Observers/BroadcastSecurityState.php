<?php

namespace App\Observers;

use App\Events\SecurityStateChanged;
use App\Models\AuthenticationLog;
use App\Models\BlockedIp;
use App\Models\Incident;
use App\Models\RequestActivity;
use App\Models\SecurityAlert;
use App\Models\SecurityEvent;
use Illuminate\Database\Eloquent\Model;

class BroadcastSecurityState
{
    public function created(Model $model): void
    {
        $this->dispatch($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->dispatch($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->dispatch($model, 'deleted');
    }

    private function dispatch(Model $model, string $action): void
    {
        $payload = match (true) {
            $model instanceof SecurityAlert => [
                'id' => $model->id, 'identifier' => $model->alert_id, 'title' => $model->title,
                'type' => $model->alert_type, 'severity' => $model->severity, 'status' => $model->status,
                'source_ip' => $model->source_ip, 'occurred_at' => $model->occurred_at?->toIso8601String(),
            ],
            $model instanceof Incident => [
                'id' => $model->id, 'identifier' => $model->incident_id, 'title' => $model->title,
                'type' => $model->incident_type, 'severity' => $model->severity, 'status' => $model->status,
                'source_ip' => $model->source_ip, 'occurred_at' => $model->last_detected_at?->toIso8601String(),
            ],
            $model instanceof BlockedIp => [
                'id' => $model->id, 'ip_address' => $model->ip_address, 'action' => $model->action,
                'status' => $model->status, 'is_enabled' => (bool) $model->is_enabled,
                'expires_at' => $model->expires_at?->toIso8601String(),
            ],
            $model instanceof RequestActivity => [
                'id' => $model->id, 'request_id' => $model->request_id, 'ip_address' => $model->ip_address,
                'method' => $model->method, 'path' => $model->path, 'status_code' => $model->status_code,
                'classification' => $model->classification, 'occurred_at' => $model->occurred_at?->toIso8601String(),
            ],
            $model instanceof AuthenticationLog => [
                'id' => $model->id, 'ip_address' => $model->ip_address, 'action' => $model->action,
                'status' => $model->status, 'occurred_at' => $model->occurred_at?->toIso8601String(),
            ],
            $model instanceof SecurityEvent => [
                'id' => $model->id, 'title' => $model->title, 'type' => $model->event_type,
                'severity' => $model->severity, 'status' => $model->status, 'source_ip' => $model->source_ip,
                'occurred_at' => $model->occurred_at?->toIso8601String(),
            ],
            default => ['id' => $model->getKey()],
        };

        SecurityStateChanged::dispatch(
            match (true) {
                $model instanceof SecurityAlert => 'security_alert',
                $model instanceof Incident => 'incident',
                $model instanceof BlockedIp => 'blocked_ip',
                $model instanceof RequestActivity => 'request_activity',
                $model instanceof AuthenticationLog => 'authentication_log',
                $model instanceof SecurityEvent => 'security_event',
                default => $model->getMorphClass(),
            },
            $action,
            $model instanceof BlockedIp ? null : ($model->getAttribute('source') ?? config('intsec.source', 'intsec')),
            $payload,
        );
    }
}
