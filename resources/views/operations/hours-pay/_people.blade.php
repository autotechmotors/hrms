<div class="hp-list">
    <div class="hp-list-head">
        <span>Person</span>
        <span>Shift</span>
        <span>Hours</span>
        <span>Salary</span>
    </div>
    @forelse($people as $person)
        <a class="hp-person" href="{{ $person['url'] }}">
            <div>
                <b>{{ $person['name'] }}</b>
                <small>{{ $person['code'] }} · {{ $person['department'] }}</small>
            </div>
            <div class="hp-fact">
                <span class="et-badge {{ $person['shift'] === 'Night' ? 'is-night' : 'is-day' }}">{{ $person['shift'] }}</span>
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
        <p class="hp-empty">No people match this search.</p>
    @endforelse
</div>
<div class="hp-pager">
    <span>
        @if($people->total() > 0)
            Showing {{ $people->firstItem() }}–{{ $people->lastItem() }} of {{ $people->total() }}
        @else
            No people to show
        @endif
    </span>
    @if($people->hasPages())
        {{ $people->links('operations.hours-pay._pager') }}
    @endif
</div>
