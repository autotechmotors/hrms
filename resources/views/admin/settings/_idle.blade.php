<div id="stResults" data-live-list="settings-idle" data-page="{{ $idle->currentPage() }}" data-last="{{ $idle->lastPage() }}" data-matched="{{ $idle->total() }}">
    @if($idle->total() === 0)
        <p class="st-empty">
            @if($overdueTerm !== '')
                No one matches “{{ $overdueTerm }}”.
            @elseif($overdueTotal === 0)
                No employees are overdue right now.
            @else
                No one matches that search.
            @endif
        </p>
    @else
        <div class="st-list" data-list-rows>
            @foreach($idle as $person)
                <a class="st-row" href="{{ route('employees.show', $person) }}">
                    <span class="av st-av" style="--h:{{ ($person->id * 47) % 360 }}">{{ mb_strtoupper(mb_substr($person->full_name, 0, 1)) }}</span>
                    <span class="st-who">
                        <b>{{ $person->full_name }}</b>
                        <small>{{ $person->employee_code }}@if($person->department) · {{ $person->department->name }}@elseif($person->role) · {{ $person->role->name }}@endif</small>
                    </span>
                    <span class="b wn">{{ $blocker->idleDays($person) }} days idle</span>
                </a>
            @endforeach
        </div>
        @include('partials.list-footer', ['paginator' => $idle])
    @endif
</div>
