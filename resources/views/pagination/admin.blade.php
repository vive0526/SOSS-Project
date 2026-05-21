@if ($paginator->hasPages())
    <nav class="admin-pagination" role="navigation" aria-label="Pagination Navigation">
        <div class="admin-pagination__meta">
            @if ($paginator->firstItem() !== null)
                Showing <strong>{{ $paginator->firstItem() }}</strong> to <strong>{{ $paginator->lastItem() }}</strong> of <strong>{{ $paginator->total() }}</strong>
            @else
                Showing <strong>{{ $paginator->count() }}</strong>
            @endif
        </div>

        <div class="admin-pager" aria-label="Pagination">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="admin-pager__btn is-disabled" aria-disabled="true">Previous</span>
            @else
                <a class="admin-pager__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            <div class="admin-pager__pages" aria-label="Pagination Pages">
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span class="admin-pager__sep" aria-hidden="true">{{ $element }}</span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="admin-pager__page is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="admin-pager__page" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a class="admin-pager__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="admin-pager__btn is-disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif

