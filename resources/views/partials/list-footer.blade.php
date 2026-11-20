@php
    $paginator->appends(request()->except('page', 'partial'))->onEachSide(1);
@endphp
<div class="pgn" data-from="{{ $paginator->firstItem() ?? 0 }}" data-to="{{ $paginator->lastItem() ?? 0 }}" data-total="{{ $paginator->total() }}">
    <span class="el-range">
        @if ($paginator->total() > 0)
            {{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        @endif
    </span>
    @if ($paginator->hasPages())
        <div class="el-pages">{{ $paginator->links('partials.list-pager') }}</div>
    @endif
</div>
