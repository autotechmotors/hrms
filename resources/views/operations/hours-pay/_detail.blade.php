<div class="hp-summary">
    <div>
        <small>Hours a day</small>
        <b>{{ $hoursLabel }}</b>
        <span>{{ $sourceLabel }}</span>
    </div>
    <div>
        <small>Salary type</small>
        <b>{{ $employee->salaryTypeLabel() }}</b>
        <span>{{ $employee->isPieceSalary() ? 'Attendance only — not on salary sheets' : ($employee->isHoursSalary() ? 'Counted on salary sheets' : 'Set on the employee profile') }}</span>
    </div>
    <div>
        <small>Monthly salary</small>
        @if($employee->isPieceSalary())
            <b>Not used</b>
            <span>Piece based people have no monthly salary amount</span>
        @else
            <b>{{ $current ? '₹'.number_format((float) $current->gross_monthly, 2) : 'Not set' }}</b>
            <span>{{ $current ? '₹'.number_format((float) $current->gross_monthly, 2).' · from '.$current->effective_from->format('d M Y') : 'No amount yet' }}</span>
        @endif
    </div>
</div>
<p class="hp-sentence">Clocks belong to this person. The month below picks which dates are day and which are night. Attendance still records the real in and out.</p>

<div class="hp-grid">
    <section class="card ef-sec">
        <header>
            <div>
                <h3>{{ __('Working hours') }}</h3>
                <p>Use the department clocks, or clocks only for {{ $employee->first_name }}. Day and night are both saved.</p>
            </div>
        </header>
        <div class="cb">
            @if($canEdit)
                <form method="post" action="{{ route('hours-pay.employees.hours', $employee) }}">
                    @csrf
                    <div class="hp-choices is-split">
                        <label class="hp-choice">
                            <input type="radio" name="mode" value="department" @checked($mode === 'department')>
                            <span>
                                <b>Use department times</b>
                                <small>{{ $employee->department?->name ?? 'No department' }} clocks. This person follows them until Own times is saved.</small>
                            </span>
                        </label>
                        <label class="hp-choice">
                            <input type="radio" name="mode" value="own" @checked($mode === 'own')>
                            <span>
                                <b>Own times</b>
                                <small>Only {{ $employee->first_name }} uses these clocks. Other people stay as they are.</small>
                            </span>
                        </label>
                    </div>
                    <div class="hp-preview" data-dept-preview @if($mode === 'own') hidden @endif>
                        <article class="hp-board">
                            <header><h4>Day</h4><small>Department</small></header>
                            <p>Mon–Sat {{ $departmentWindows['weekday_start'] }}–{{ $departmentWindows['weekday_end'] }}</p>
                            <p>Sunday {{ $departmentWindows['sunday_start'] }}–{{ $departmentWindows['sunday_end'] }}</p>
                        </article>
                        <article class="hp-board">
                            <header><h4>Night</h4><small>Department</small></header>
                            <p>Mon–Sat {{ $departmentWindows['night_weekday_start'] }}–{{ $departmentWindows['night_weekday_end'] }}</p>
                            <p>Sunday {{ $departmentWindows['night_works_sunday'] ? $departmentWindows['night_sunday_start'].'–'.$departmentWindows['night_sunday_end'] : 'Off · paid, no punch' }}</p>
                        </article>
                    </div>
                    @php
                        $nightOn = $shiftKind === 'night' && $mode === 'own'
                            ? (bool) ($employee->workHour?->works_sunday)
                            : (bool) $departmentWindows['night_works_sunday'];
                    @endphp
                    <div class="hp-own" data-own-hours @if($mode !== 'own') hidden @endif>
                        <div class="hp-boards">
                        <section class="hp-board">
                            <header><h4>Day</h4></header>
                            <div class="hp-clock">
                                <span>Monday–Saturday</span>
                                <div class="hp-line">
                                    <b>{{ __('Work') }}</b>
                                    <input class="form-control" type="time" name="weekday_start" value="{{ $windows['weekday_start'] }}" @if($mode === 'own') required @endif aria-label="Day start">
                                    <em>to</em>
                                    <input class="form-control" type="time" name="weekday_end" value="{{ $windows['weekday_end'] }}" @if($mode === 'own') required @endif aria-label="Day end">
                                </div>
                                <div class="hp-line">
                                    <b>{{ __('Break') }}</b>
                                    <input class="form-control" type="time" name="weekday_break_start" value="{{ $windows['weekday_break_start'] }}" aria-label="Day break start">
                                    <em>to</em>
                                    <input class="form-control" type="time" name="weekday_break_end" value="{{ $windows['weekday_break_end'] }}" aria-label="Day break end">
                                </div>
                            </div>
                            <div class="hp-clock">
                                <span>Sunday</span>
                                <div class="hp-line">
                                    <b>{{ __('Work') }}</b>
                                    <input class="form-control" type="time" name="sunday_start" value="{{ $windows['sunday_start'] }}" @if($mode === 'own') required @endif aria-label="Sunday start">
                                    <em>to</em>
                                    <input class="form-control" type="time" name="sunday_end" value="{{ $windows['sunday_end'] }}" @if($mode === 'own') required @endif aria-label="Sunday end">
                                </div>
                                <div class="hp-line">
                                    <b>{{ __('Break') }}</b>
                                    <input class="form-control" type="time" name="sunday_break_start" value="{{ $windows['sunday_break_start'] }}" aria-label="Sunday break start">
                                    <em>to</em>
                                    <input class="form-control" type="time" name="sunday_break_end" value="{{ $windows['sunday_break_end'] }}" aria-label="Sunday break end">
                                </div>
                            </div>
                        </section>
                        <section class="hp-board">
                            <header><h4>Night</h4></header>
                            <div class="hp-clock">
                                <span>Monday–Saturday</span>
                                <div class="hp-line">
                                    <b>{{ __('Work') }}</b>
                                    <input class="form-control" type="time" name="night_weekday_start" value="{{ $windows['night_weekday_start'] }}" @if($mode === 'own') required @endif aria-label="Night start">
                                    <em>to</em>
                                    <input class="form-control" type="time" name="night_weekday_end" value="{{ $windows['night_weekday_end'] }}" @if($mode === 'own') required @endif aria-label="Night end">
                                </div>
                                <div class="hp-line">
                                    <b>{{ __('Break') }}</b>
                                    <input class="form-control" type="time" name="night_weekday_break_start" value="{{ $windows['night_weekday_break_start'] }}" aria-label="Night break start">
                                    <em>to</em>
                                    <input class="form-control" type="time" name="night_weekday_break_end" value="{{ $windows['night_weekday_break_end'] }}" aria-label="Night break end">
                                </div>
                            </div>
                            <div class="hp-clock" data-person-night>
                                <div class="hp-sunday-top">
                                    <span>Sunday</span>
                                    <input type="hidden" name="works_sunday" value="0">
                                    <label class="et-switch">
                                        <input type="checkbox" name="works_sunday" value="1" data-night-sunday @checked($nightOn)>
                                        <span class="rl-ui" aria-hidden="true"></span>
                                        <span class="et-switch-txt" data-night-sunday-label>{{ $nightOn ? 'On' : 'Off' }}</span>
                                    </label>
                                </div>
                                <p class="hp-night-note" data-night-sunday-off @if($nightOn) hidden @endif>Off. Paid Sunday, no punch and no cutoff.</p>
                                <div class="hp-line" data-night-sunday-times @unless($nightOn) hidden @endunless>
                                    <b>{{ __('Work') }}</b>
                                    <input class="form-control" type="time" name="night_sunday_start" value="{{ $windows['night_sunday_start'] }}" aria-label="Sunday night start">
                                    <em>to</em>
                                    <input class="form-control" type="time" name="night_sunday_end" value="{{ $windows['night_sunday_end'] }}" aria-label="Sunday night end">
                                </div>
                                <div class="hp-line" data-night-sunday-times @unless($nightOn) hidden @endunless>
                                    <b>{{ __('Break') }}</b>
                                    <input class="form-control" type="time" name="night_sunday_break_start" value="{{ $windows['night_sunday_break_start'] ?: '01:00' }}" aria-label="Sunday night break start">
                                    <em>to</em>
                                    <input class="form-control" type="time" name="night_sunday_break_end" value="{{ $windows['night_sunday_break_end'] ?: '02:00' }}" aria-label="Sunday night break end">
                                </div>
                            </div>
                        </section>
                        </div>
                    </div>
                    <input type="hidden" name="shift_kind" value="{{ $shiftKind }}">
                    <button class="btn pr" type="submit">Save hours</button>
                </form>
            @else
                <p class="ed-empty">{{ $sentence }}</p>
            @endif
        </div>
    </section>

    @include('employees._shift_month', ['employee' => $employee])

    @if($employee->isPieceSalary())
        <section class="card ef-sec">
            <header>
                <div>
                    <h3>{{ __('Salary') }}</h3>
                    <p>Piece based — attendance only</p>
                </div>
            </header>
            <div class="cb">
                <p class="ed-empty">This person is piece based. Monthly salary is not set or counted here. Use Attendance for the month record.</p>
            </div>
        </section>
    @else
        <section class="card ef-sec">
            <header>
                <div>
                    <h3>{{ __('Salary') }}</h3>
                    <p>The monthly amount for this hours-based person.</p>
                </div>
            </header>
            <div class="cb">
                @if(! $employee->isHoursSalary())
                    <p class="ed-empty">Set salary type to Hours based on the employee profile before saving a monthly amount.</p>
                @elseif($canEdit)
                    <form method="post" action="{{ route('hours-pay.employees.salary', $employee) }}">
                        @csrf
                        <div class="hp-pay">
                            <div>
                                <label class="form-label" for="gross_monthly">Monthly salary</label>
                                <input class="form-control" id="gross_monthly" type="number" name="gross_monthly" min="0.01" step="0.01" inputmode="decimal" value="{{ $current ? $current->gross_monthly : '' }}" required>
                            </div>
                            <div>
                                <label class="form-label" for="effective_from">Starts on</label>
                                <input class="form-control" id="effective_from" type="date" name="effective_from" value="{{ $current?->effective_from?->toDateString() ?: now()->toDateString() }}" required>
                            </div>
                            <button class="btn pr" type="submit">Save salary</button>
                        </div>
                    </form>
                @elseif(! $current)
                    <p class="ed-empty">No salary set.</p>
                @endif

                @if($history->isNotEmpty())
                    <div class="hp-history">
                        @foreach($history as $row)
                            <div>
                                <b>₹{{ number_format((float) $row->gross_monthly, 2) }}</b>
                                <small>From {{ $row->effective_from->format('d M Y') }}@if($current && $current->is($row)) · current @endif</small>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>
