@props(['severity'])
@php
    $tone = match ($severity) {
        'Critical' => 'ops-badge--red',
        'High' => 'ops-badge--orange',
        'Warning', 'Suspicious', 'Medium' => 'ops-badge--amber',
        'Normal', 'Low' => 'ops-badge--green',
        default => 'ops-badge--muted',
    };
@endphp
<span {{ $attributes->class(['ops-badge', $tone]) }}>{{ $severity ?: 'Unknown' }}</span>
