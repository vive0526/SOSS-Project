@if ($paginator->hasPages())
    <nav class="customer-pagination customer-pagination--panel" role="navigation" aria-label="Pagination Navigation">
        <div class="customer-pagination__meta">
            @if ($paginator->firstItem() !== null)
                Showing
                <strong>{{ $paginator->firstItem() }}</strong>
                to
                <strong>{{ $paginator->lastItem() }}</strong>
                of
                <strong>{{ $paginator->total() }}</strong>
                results
            @else
                Showing <strong>{{ $paginator->count() }}</strong> results
            @endif
        </div>

        <div class="customer-pager" aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="customer-pager__btn is-disabled" aria-disabled="true">Previous</span>
            @else
                <a class="customer-pager__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            <div class="customer-pager__pages" aria-label="Pagination Pages">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="customer-pager__sep" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="customer-pager__page is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="customer-pager__page" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a class="customer-pager__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="customer-pager__btn is-disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@elseif ($paginator->total() > 0)
    <div class="customer-pagination customer-pagination--panel">
        <div class="customer-pagination__meta">
            Showing <strong>{{ $paginator->total() }}</strong> results
        </div>
    </div>
@endif
