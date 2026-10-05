@props(['status'])
@php
    $tone = match ($status) {
        'active', 'enabled', 'allowed', 'resolved', 'closed', 'successful', 'processed' => 'ops-badge--green',
        'new', 'acknowledged' => 'ops-badge--cyan',
        'investigating', 'contained', 'warning' => 'ops-badge--amber',
        'blocked', 'failed', 'critical' => 'ops-badge--red',
        'high' => 'ops-badge--orange',
        default => 'ops-badge--muted',
    };
@endphp
<span {{ $attributes->class(['ops-badge', $tone]) }}>{{ ucwords(str_replace('_', ' ', $status ?: 'unknown')) }}</span>
