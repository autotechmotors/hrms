<div class="hp-summary">
    <div>
        <small>Hours a day</small>
        <b>{{ $hoursLabel }}</b>
        <span>{{ $sourceLabel }}</span>
    </div>
    <div>
        <small>Monthly salary</small>
        <b>{{ $current ? '₹'.number_format((float) $current->gross_monthly, 2) : 'Not set' }}</b>
        <span>{{ $current ? ($current->structure?->name ?? 'Structure').' · from '.$current->effective_from->format('d M Y') : 'No amount yet' }}</span>
    </div>
</div>
<p class="hp-sentence">{{ $sentence }} Attendance still records the real in and out. These hours are the day length you expect.</p>

<div class="hp-grid">
    <section class="card ef-sec">
        <header>
            <div>
                <h3>Working hours</h3>
                <p>Department hours, or hours only for this person.</p>
            </div>
        </header>
        <div class="cb">
            @if($canEdit)
                <form method="post" action="{{ route('hours-pay.employees.hours', $employee) }}">
                    @csrf
                    <div class="hp-choices">
                        <label class="hp-choice">
                            <input type="radio" name="shift_kind" value="day" @checked($shiftKind === 'day')>
                            <span>
                                <b>Day shift</b>
                                <small>{{ $departmentWindows['weekday_start'] }}–{{ $departmentWindows['weekday_end'] }}, Sunday {{ $departmentWindows['sunday_start'] }}–{{ $departmentWindows['sunday_end'] }}</small>
                            </span>
                        </label>
                        <label class="hp-choice">
                            <input type="radio" name="shift_kind" value="night" @checked($shiftKind === 'night')>
                            <span>
                                <b>Night shift</b>
                                <small>{{ $departmentWindows['night_weekday_start'] }}–{{ $departmentWindows['night_weekday_end'] }} Monday to Saturday. Sunday is paid. No night duty.</small>
                            </span>
                        </label>
                    </div>
                    <div class="hp-choices">
                        <label class="hp-choice">
                            <input type="radio" name="mode" value="department" @checked($mode === 'department')>
                            <span>
                                <b>Same as department</b>
                                <small>{{ $employee->department?->name ?? 'No department' }} · {{ $departmentWindows['weekday_start'] }}–{{ $departmentWindows['weekday_end'] }}, Sunday {{ $departmentWindows['sunday_start'] }}–{{ $departmentWindows['sunday_end'] }}</small>
                            </span>
                        </label>
                        <label class="hp-choice">
                            <input type="radio" name="mode" value="own" @checked($mode === 'own')>
                            <span>
                                <b>Own hours</b>
                                <small>Only {{ $employee->first_name }} uses these clocks.</small>
                            </span>
                        </label>
                    </div>
                    <div class="hp-own" data-own-hours @if($mode !== 'own') hidden @endif>
                        <div class="hp-times">
                            <span>Day · Mon–Sat</span>
                            <input class="form-control" type="time" name="weekday_start" value="{{ $windows['weekday_start'] }}" @if($mode === 'own') required @endif>
                            <input class="form-control" type="time" name="weekday_end" value="{{ $windows['weekday_end'] }}" @if($mode === 'own') required @endif>
                        </div>
                        <div class="hp-times">
                            <span>Day break</span>
                            <input class="form-control" type="time" name="weekday_break_start" value="{{ $windows['weekday_break_start'] }}" aria-label="Day break start">
                            <input class="form-control" type="time" name="weekday_break_end" value="{{ $windows['weekday_break_end'] }}" aria-label="Day break end">
                        </div>
                        <div class="hp-times">
                            <span>Day · Sunday</span>
                            <input class="form-control" type="time" name="sunday_start" value="{{ $windows['sunday_start'] }}" @if($mode === 'own') required @endif>
                            <input class="form-control" type="time" name="sunday_end" value="{{ $windows['sunday_end'] }}" @if($mode === 'own') required @endif>
                        </div>
                        <div class="hp-times">
                            <span>Sunday break</span>
                            <input class="form-control" type="time" name="sunday_break_start" value="{{ $windows['sunday_break_start'] }}" aria-label="Sunday break start">
                            <input class="form-control" type="time" name="sunday_break_end" value="{{ $windows['sunday_break_end'] }}" aria-label="Sunday break end">
                        </div>
                        <div class="hp-times">
                            <span>Night · Mon–Sat</span>
                            <input class="form-control" type="time" name="night_weekday_start" value="{{ $windows['night_weekday_start'] }}" @if($mode === 'own') required @endif>
                            <input class="form-control" type="time" name="night_weekday_end" value="{{ $windows['night_weekday_end'] }}" @if($mode === 'own') required @endif>
                        </div>
                        <div class="hp-times">
                            <span>Night break</span>
                            <input class="form-control" type="time" name="night_weekday_break_start" value="{{ $windows['night_weekday_break_start'] }}" aria-label="Night break start">
                            <input class="form-control" type="time" name="night_weekday_break_end" value="{{ $windows['night_weekday_break_end'] }}" aria-label="Night break end">
                        </div>
                    </div>
                    <button class="btn pr" type="submit">Save hours</button>
                </form>
            @else
                <p class="ed-empty">{{ $sentence }}</p>
            @endif
        </div>
    </section>

    <section class="card ef-sec">
        <header>
            <div>
                <h3>Salary</h3>
                <p>The monthly amount for this person.</p>
            </div>
        </header>
        <div class="cb">
            @if($canEdit)
                @if($structures->isEmpty())
                    <p class="ed-empty">Add a salary structure first, then come back to set this person’s amount.</p>
                    <a class="btn" href="{{ route('salary.index') }}">Open salary structures</a>
                @else
                    <form method="post" action="{{ route('hours-pay.employees.salary', $employee) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="salary_structure_id">Structure</label>
                            <select class="form-select" id="salary_structure_id" name="salary_structure_id" data-native required>
                                <option value="">Select</option>
                                @foreach($structures as $structure)
                                    <option value="{{ $structure->id }}" @selected($current && (int) $current->salary_structure_id === (int) $structure->id)>{{ $structure->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gross_monthly">Monthly salary</label>
                            <input class="form-control" id="gross_monthly" type="number" name="gross_monthly" min="0" step="0.01" inputmode="decimal" value="{{ $current ? $current->gross_monthly : '' }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="effective_from">Starts on</label>
                            <input class="form-control" id="effective_from" type="date" name="effective_from" value="{{ $current?->effective_from?->toDateString() ?: now()->toDateString() }}" required>
                        </div>
                        <button class="btn pr" type="submit">Save salary</button>
                    </form>
                @endif
            @elseif(! $current)
                <p class="ed-empty">No salary set.</p>
            @endif

            @if($history->isNotEmpty())
                <div class="hp-history">
                    @foreach($history as $row)
                        <div>
                            <b>₹{{ number_format((float) $row->gross_monthly, 2) }}</b>
                            <small>{{ $row->structure?->name ?? 'Structure' }} · from {{ $row->effective_from->format('d M Y') }}@if($current && $current->is($row)) · current @endif</small>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
