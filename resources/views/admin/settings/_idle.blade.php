<div id="stResults" data-matched="{{ $idle->total() }}">
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
        <div class="st-list">
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
        <div class="st-foot">
            <p class="st-range">Showing {{ $idle->firstItem() }}–{{ $idle->lastItem() }} of {{ $idle->total() }}</p>
            @if($idle->hasPages())
                <nav class="el-pager" aria-label="Overdue pages">
                    @if($idle->onFirstPage())
                        <span class="is-off" aria-hidden="true">‹</span>
                    @else
                        <a href="{{ $idle->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
                    @endif
                    @foreach($idle->linkCollection() as $link)
                        @if($link['label'] === '...')
                            <span class="is-gap">…</span>
                        @elseif(is_numeric($link['label']))
                            @if($link['active'])
                                <span class="is-on" aria-current="page">{{ $link['label'] }}</span>
                            @else
                                <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                            @endif
                        @endif
                    @endforeach
                    @if($idle->hasMorePages())
                        <a href="{{ $idle->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
                    @else
                        <span class="is-off" aria-hidden="true">›</span>
                    @endif
                </nav>
            @endif
        </div>
    @endif
</div>
