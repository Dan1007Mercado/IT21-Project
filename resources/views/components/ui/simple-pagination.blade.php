@props(['paginator', 'label' => 'results'])

@if ($paginator->hasPages() || $paginator->count())
    <nav class="ops-pagination" aria-label="{{ ucfirst($label) }} pagination">
        <p>
            Showing {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }}
            {{ $label }} on page {{ number_format($paginator->currentPage()) }}
        </p>
        <div class="ops-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="ops-pagination-link" aria-disabled="true">← Previous</span>
            @else
                <a class="ops-pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Previous</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="ops-pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next →</a>
            @else
                <span class="ops-pagination-link" aria-disabled="true">Next →</span>
            @endif
        </div>
    </nav>
@endif
