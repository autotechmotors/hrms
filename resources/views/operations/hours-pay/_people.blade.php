<div data-live-list="hours-pay" data-page="{{ $people->currentPage() }}" data-last="{{ $people->lastPage() }}">
    <div class="hp-list">
        <div class="hp-list-head">
            <span>{{ __('Person') }}</span>
            <span>{{ __('Shift') }}</span>
            <span>{{ __('Hours') }}</span>
            <span>{{ __('Salary') }}</span>
        </div>
        <div data-list-rows>
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
                <p class="hp-empty" data-list-empty>{{ __('No people match this search.') }}</p>
            @endforelse
        </div>
    </div>
    @include('partials.list-footer', ['paginator' => $people])
</div>
