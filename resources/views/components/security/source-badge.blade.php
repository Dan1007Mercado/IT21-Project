@props(['source'])
@php
    $hotel = $source === \App\Enums\MonitoringSource::HotelBooking->value;
    $label = config('intsec.sources.'.$source, str($source)->replace('-', ' ')->title());
@endphp
<span {{ $attributes->class([
    'inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold',
    'border-amber-500/30 bg-amber-500/10 text-amber-200' => $hotel,
    'border-cyan-500/30 bg-cyan-500/10 text-cyan-200' => ! $hotel,
]) }}>{{ $label }}</span>
