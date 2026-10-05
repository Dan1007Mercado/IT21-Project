@props(['source'])
@php
    $hotel = $source === \App\Enums\MonitoringSource::HotelBooking->value;
    $label = config('intsec.sources.'.$source, str($source)->replace('-', ' ')->title());
@endphp
<span {{ $attributes->class(['ops-badge', 'ops-badge--amber' => $hotel, 'ops-badge--cyan' => ! $hotel]) }}>{{ $label }}</span>
