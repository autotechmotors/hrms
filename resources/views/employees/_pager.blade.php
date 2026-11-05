@if ($paginator->hasPages())
    <nav class="el-pager" aria-label="Employee pages">
        @if ($paginator->onFirstPage())
            <span class="is-off" aria-hidden="true">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="is-gap">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="is-on" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
        @else
            <span class="is-off" aria-hidden="true">›</span>
        @endif
    </nav>
@endif
