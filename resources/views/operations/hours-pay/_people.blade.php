<div class="hp-people">
    @forelse($people as $person)
        <a class="hp-person" href="{{ $person['url'] }}">
            <div>
                <b>{{ $person['name'] }}</b>
                <small>{{ $person['code'] }} · {{ $person['department'] }}</small>
            </div>
            <div class="hp-fact">
                <b>{{ $person['hours'] }}</b>
                <small>{{ $person['source'] }}</small>
            </div>
            <div class="hp-fact">
                <b>{{ $person['salary'] }}</b>
                <small>{{ $person['salaryNote'] }}</small>
            </div>
        </a>
    @empty
        <p class="ed-empty">No people match this search.</p>
    @endforelse
</div>
@if($people->total() > 0)
    <div class="hp-pager">
        <span>Showing {{ $people->firstItem() }}–{{ $people->lastItem() }} of {{ $people->total() }}</span>
        @if($people->hasPages())
            <div>
                @if($people->onFirstPage())
                    <span class="btn sm" aria-disabled="true">Previous</span>
                @else
                    <a class="btn sm" href="{{ $people->previousPageUrl() }}">Previous</a>
                @endif
                @if($people->hasMorePages())
                    <a class="btn sm" href="{{ $people->nextPageUrl() }}">Next</a>
                @else
                    <span class="btn sm" aria-disabled="true">Next</span>
                @endif
            </div>
        @endif
    </div>
@endif
