@props([
    'kicker',
    'title',
    'description' => null,
])

<header {{ $attributes->class('ops-page-header') }}>
    <div class="ops-page-heading">
        <p class="ops-kicker">{{ $kicker }}</p>
        <h1 class="ops-title">{{ $title }}</h1>
        @if ($description)
            <p class="ops-description">{{ $description }}</p>
        @endif
        @if (isset($context))
            <div class="ops-context-strip">{{ $context }}</div>
        @endif
    </div>

    @if (isset($actions))
        <div class="ops-header-actions">{{ $actions }}</div>
    @endif
</header>
