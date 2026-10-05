@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('ops-empty') }}>
    <p class="ops-empty-title">{{ $title }}</p>
    @if ($description)
        <p class="ops-empty-description">{{ $description }}</p>
    @endif
    @if (trim((string) $slot) !== '')
        <div class="ops-action-group mt-4 justify-center">{{ $slot }}</div>
    @endif
</div>
