@php
    $postedSunday = old('works_sunday');
    $worksSunday = $postedSunday === null
        ? (bool) $times['works_sunday']
        : filter_var($postedSunday, FILTER_VALIDATE_BOOLEAN);
    $weekStart = old('weekday_start', $times['weekday_start']);
    $weekEnd = old('weekday_end', $times['weekday_end']);
    $sunStart = old('sunday_start', $times['sunday_start']);
    $sunEnd = old('sunday_end', $times['sunday_end']);
    $weekBreakStart = old('weekday_break_start', $times['weekday_break_start'] ?? '');
    $weekBreakEnd = old('weekday_break_end', $times['weekday_break_end'] ?? '');
    $sunBreakStart = old('sunday_break_start', $times['sunday_break_start'] ?? '');
    $sunBreakEnd = old('sunday_break_end', $times['sunday_break_end'] ?? '');
    $editable = (bool) ($canEditTimes ?? false);
    $standalone = (bool) ($standalone ?? false);
    $kind = old('shift_kind', $times['shift_kind'] ?? 'day') === 'night' ? 'night' : 'day';
@endphp
<section class="card ef-sec" id="empTimes">
    <header>
        <div>
            <h3>{{ __('Working times') }}</h3>
            <p>Work, then break. Sunday uses the switch. Night uses this person’s Sunday night.</p>
        </div>
        <span class="et-badge {{ $kind === 'night' ? 'is-night' : 'is-day' }}" data-kind-badge>{{ $kind === 'night' ? 'Night' : 'Day' }}</span>
    </header>
    <div class="cb">
        @if($editable)
            @if($standalone)
                <form id="empTimesForm" method="post" action="{{ route('employees.times', $employee) }}">
                    @csrf
            @endif
            <div class="et-times" data-kind-clocks
                data-day-start="{{ $times['day_start'] }}"
                data-day-end="{{ $times['day_end'] }}"
                data-night-start="{{ $times['night_start'] }}"
                data-night-end="{{ $times['night_end'] }}"
                data-day-sunday-start="{{ $times['day_sunday_start'] }}"
                data-day-sunday-end="{{ $times['day_sunday_end'] }}"
                data-night-sunday-start="{{ $times['night_sunday_start'] }}"
                data-night-sunday-end="{{ $times['night_sunday_end'] }}"
                data-day-break-start="{{ $times['day_break_start'] }}"
                data-day-break-end="{{ $times['day_break_end'] }}"
                data-night-break-start="{{ $times['night_break_start'] }}"
                data-night-break-end="{{ $times['night_break_end'] }}"
                data-day-sunday-break-start="{{ $times['day_sunday_break_start'] }}"
                data-day-sunday-break-end="{{ $times['day_sunday_break_end'] }}"
                data-night-sunday-break-start="{{ $times['night_sunday_break_start'] }}"
                data-night-sunday-break-end="{{ $times['night_sunday_break_end'] }}">
                <div class="et-kinds">
                    <label class="et-kind">
                        <input type="radio" name="shift_kind" value="day" data-kind @checked($kind === 'day')>
                        <span><b>Day</b><small>Monday to Saturday day clock</small></span>
                    </label>
                    <label class="et-kind">
                        <input type="radio" name="shift_kind" value="night" data-kind @checked($kind === 'night')>
                        <span><b>Night</b><small>Monday to Saturday night clock</small></span>
                    </label>
                </div>
                <div class="et-board">
                    <div class="et-clock">
                        <span>Monday to Saturday</span>
                        <div class="hp-line">
                            <b>{{ __('Work') }}</b>
                            <input class="form-control" type="time" name="weekday_start" value="{{ $weekStart }}" required aria-label="Monday to Saturday start">
                            <em>to</em>
                            <input class="form-control" type="time" name="weekday_end" value="{{ $weekEnd }}" required aria-label="Monday to Saturday end">
                        </div>
                        <div class="hp-line">
                            <b>{{ __('Break') }}</b>
                            <input class="form-control" type="time" name="weekday_break_start" value="{{ $weekBreakStart }}" aria-label="Monday to Saturday break start">
                            <em>to</em>
                            <input class="form-control" type="time" name="weekday_break_end" value="{{ $weekBreakEnd }}" aria-label="Monday to Saturday break end">
                        </div>
                    </div>
                    <div class="et-sunday">
                        <div class="et-sunday-top">
                            <b data-sunday-title>{{ $kind === 'night' ? 'Sunday night' : 'Sunday' }}</b>
                            <input type="hidden" name="works_sunday" value="0">
                            <label class="et-switch">
                                <input type="checkbox" name="works_sunday" value="1" data-sunday @checked($worksSunday)>
                                <span class="rl-ui" aria-hidden="true"></span>
                                <span class="et-switch-txt">{{ $worksSunday ? 'On' : 'Off' }}</span>
                            </label>
                        </div>
                        <div class="hp-line" data-sunday-times @unless($worksSunday) hidden @endunless>
                            <b>{{ __('Work') }}</b>
                            <input class="form-control" type="time" name="sunday_start" value="{{ $sunStart }}" @if($worksSunday) required @endif aria-label="Sunday start">
                            <em>to</em>
                            <input class="form-control" type="time" name="sunday_end" value="{{ $sunEnd }}" @if($worksSunday) required @endif aria-label="Sunday end">
                        </div>
                        <div class="hp-line" data-sunday-times @unless($worksSunday) hidden @endunless>
                            <b>{{ __('Break') }}</b>
                            <input class="form-control" type="time" name="sunday_break_start" value="{{ $sunBreakStart }}" aria-label="Sunday break start">
                            <em>to</em>
                            <input class="form-control" type="time" name="sunday_break_end" value="{{ $sunBreakEnd }}" aria-label="Sunday break end">
                        </div>
                        <p class="et-off" data-sunday-off @if($worksSunday) hidden @endif>Off. Paid day, no punch and no cutoff.</p>
                    </div>
                </div>
                @error('weekday_start')<p class="et-error">{{ $message }}</p>@enderror
                @error('weekday_end')<p class="et-error">{{ $message }}</p>@enderror
                @error('sunday_start')<p class="et-error">{{ $message }}</p>@enderror
                @error('sunday_end')<p class="et-error">{{ $message }}</p>@enderror
                @if($standalone)
                    <div class="et-save">
                        <button class="btn pr sm" type="submit">Save times</button>
                    </div>
                @endif
            </div>
            @if($standalone)
                </form>
            @endif
        @else
            <div class="et-read">
                <div>
                    <span>Monday to Saturday</span>
                    <b>Work {{ $times['weekday_start'] }}–{{ $times['weekday_end'] }}</b>
                    <small>{{ ($times['weekday_break_start'] ?? '') !== '' ? 'Break '.$times['weekday_break_start'].'–'.$times['weekday_break_end'] : 'No break' }}</small>
                </div>
                <div>
                    <span>{{ $kind === 'night' ? 'Sunday night' : 'Sunday' }}</span>
                    @if($kind === 'night')
                        <b>{{ ($times['works_sunday'] ?? false) ? 'Work '.$times['sunday_start'].'–'.$times['sunday_end'] : 'Off' }}</b>
                        <small>{{ ($times['works_sunday'] ?? false) && ($times['sunday_break_start'] ?? '') !== '' ? 'Break '.$times['sunday_break_start'].'–'.$times['sunday_break_end'] : 'Paid, no cutoff' }}</small>
                    @else
                        <b>{{ $times['works_sunday'] ? 'Work '.$times['sunday_start'].'–'.$times['sunday_end'] : 'Off' }}</b>
                        <small>{{ $times['works_sunday'] && ($times['sunday_break_start'] ?? '') !== '' ? 'Break '.$times['sunday_break_start'].'–'.$times['sunday_break_end'] : 'Paid, no cutoff' }}</small>
                    @endif
                </div>
            </div>
        @endif
    </div>
</section>
