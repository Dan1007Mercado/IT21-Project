<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'title',
        'description',
        'incident_type',
        'severity',
        'status',
        'source_ip',
        'user_id',
        'security_event_id',
        'assigned_to',
        'assigned_at',
        'detection_reason',
        'detection_rule',
        'event_count',
        'first_detected_at',
        'last_detected_at',
        'acknowledged_at',
        'contained_at',
        'resolved_at',
        'response_actions',
        'resolution_notes',
    ];

    protected $casts = [
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'contained_at' => 'datetime',
        'resolved_at' => 'datetime',
        'assigned_at' => 'datetime',
        'event_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $incident): void {
            if (empty($incident->incident_id)) {
                $incident->incident_id = self::generateIncidentId();
            }
        });

        static::created(function (self $incident): void {
            $incident->statusHistory()->firstOrCreate([
                'previous_status' => null,
                'new_status' => $incident->status,
            ], [
                'actor_id' => $incident->assigned_to,
                'reason' => 'Incident created',
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdministrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function securityEvent(): BelongsTo
    {
        return $this->belongsTo(SecurityEvent::class);
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(IncidentRemark::class)->latest('created_at');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(IncidentStatusHistory::class)->latest('created_at');
    }

    public static function generateIncidentId(): string
    {
        $year = now()->format('Y');
        $prefix = "INC-{$year}-";
        $latest = static::query()
            ->where('incident_id', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('incident_id');

        $sequence = 1;
        if ($latest !== null && preg_match('/^(?:INC-\d{4}-)(\d{6})$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        do {
            $candidate = sprintf('INC-%s-%06d', $year, $sequence);
            if (! static::query()->where('incident_id', $candidate)->exists()) {
                return $candidate;
            }

            $sequence++;
        } while (true);
    }

    public function updateStatus(string $newStatus, ?User $actor = null, ?string $reason = null): void
    {
        $previousStatus = $this->status;

        if ($previousStatus === $newStatus) {
            return;
        }

        $this->status = $newStatus;

        if ($newStatus === 'contained' && ! $this->contained_at) {
            $this->contained_at = now();
        }

        if ($newStatus === 'resolved' && ! $this->resolved_at) {
            $this->resolved_at = now();
        }

        if ($newStatus === 'investigating' && ! $this->acknowledged_at) {
            $this->acknowledged_at = now();
        }

        $this->save();

        $this->statusHistory()->create([
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'actor_id' => $actor?->id,
            'reason' => $reason,
        ]);
    }

    public function timelineEntries(): 
        \Illuminate\Support\Collection
    {
        $entries = collect();

        if ($this->securityEvent) {
            $entries->push([
                'timestamp' => $this->first_detected_at ?? $this->securityEvent->occurred_at,
                'title' => 'Security event detected',
                'detail' => $this->securityEvent->title.' ('.$this->securityEvent->event_type.')',
                'type' => 'security_event',
            ]);
        }

        if ($this->first_detected_at) {
            $entries->push([
                'timestamp' => $this->first_detected_at,
                'title' => 'First evidence observed',
                'detail' => 'Initial event recorded at '.$this->first_detected_at->format('Y-m-d H:i:s').' from '.$this->source_ip,
                'type' => 'detection',
            ]);
        }

        if ($this->statusHistory) {
            foreach ($this->statusHistory as $history) {
                $entries->push([
                    'timestamp' => $history->created_at,
                    'title' => 'Status changed',
                    'detail' => ($history->previous_status ? ucfirst($history->previous_status) : 'Open').' → '.ucfirst($history->new_status).($history->reason ? ': '.$history->reason : ''),
                    'type' => 'status',
                ]);
            }
        }

        if ($this->remarks) {
            foreach ($this->remarks as $remark) {
                $entries->push([
                    'timestamp' => $remark->created_at,
                    'title' => 'Investigation remark',
                    'detail' => $remark->remark,
                    'type' => 'remark',
                ]);
            }
        }

        if ($this->acknowledged_at) {
            $entries->push([
                'timestamp' => $this->acknowledged_at,
                'title' => 'Incident acknowledged',
                'detail' => 'Investigation initiated by '.$this->assignedAdministrator?->name,
                'type' => 'ack',
            ]);
        }

        if ($this->contained_at) {
            $entries->push([
                'timestamp' => $this->contained_at,
                'title' => 'Threat contained',
                'detail' => 'Containment actions completed.',
                'type' => 'containment',
            ]);
        }

        if ($this->resolved_at) {
            $entries->push([
                'timestamp' => $this->resolved_at,
                'title' => 'Incident resolved',
                'detail' => $this->resolution_notes ?: 'The incident was closed as resolved.',
                'type' => 'resolution',
            ]);
        }

        return $entries
            ->sortBy(fn ($entry) => $entry['timestamp'] ?? now())
            ->values();
    }
}
