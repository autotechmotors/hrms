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
            <h3>Working times</h3>
            <p>Choose Day or Night, then set this person’s clocks. Sunday applies only when it is on.</p>
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
                <label class="et-clock">
                    <span>Monday to Saturday</span>
                    <div>
                        <input class="form-control" type="time" name="weekday_start" value="{{ $weekStart }}" required aria-label="Monday to Saturday start">
                        <em>to</em>
                        <input class="form-control" type="time" name="weekday_end" value="{{ $weekEnd }}" required aria-label="Monday to Saturday end">
                    </div>
                </label>
                <div class="et-clock et-break">
                    <span>Monday to Saturday break</span>
                    <div>
                        <input class="form-control" type="time" name="weekday_break_start" value="{{ $weekBreakStart }}" aria-label="Monday to Saturday break start">
                        <em>to</em>
                        <input class="form-control" type="time" name="weekday_break_end" value="{{ $weekBreakEnd }}" aria-label="Monday to Saturday break end">
                    </div>
                    <small>Inside this clock. 13:00 to 14:00 is 60 minutes. Leave blank for no break.</small>
                </div>
                @error('weekday_start')<p class="et-error">{{ $message }}</p>@enderror
                @error('weekday_end')<p class="et-error">{{ $message }}</p>@enderror
                <div class="et-sunday">
                    <div class="et-sunday-top">
                        <div>
                            <b>Sunday</b>
                            <small>On sets the Sunday clock. Off pays the day, with no punch and no cutoff.</small>
                        </div>
                        <input type="hidden" name="works_sunday" value="0">
                        <label class="et-switch">
                            <input type="checkbox" name="works_sunday" value="1" data-sunday @checked($worksSunday)>
                            <span class="rl-ui" aria-hidden="true"></span>
                            <span class="et-switch-txt">{{ $worksSunday ? 'On' : 'Off' }}</span>
                        </label>
                    </div>
                    <label class="et-clock" data-sunday-times @unless($worksSunday) hidden @endunless>
                        <span>Sunday time</span>
                        <div>
                            <input class="form-control" type="time" name="sunday_start" value="{{ $sunStart }}" @if($worksSunday) required @endif aria-label="Sunday start">
                            <em>to</em>
                            <input class="form-control" type="time" name="sunday_end" value="{{ $sunEnd }}" @if($worksSunday) required @endif aria-label="Sunday end">
                        </div>
                    </label>
                    <div class="et-clock et-break" data-sunday-times @unless($worksSunday) hidden @endunless>
                        <span>Sunday break</span>
                        <div>
                            <input class="form-control" type="time" name="sunday_break_start" value="{{ $sunBreakStart }}" aria-label="Sunday break start">
                            <em>to</em>
                            <input class="form-control" type="time" name="sunday_break_end" value="{{ $sunBreakEnd }}" aria-label="Sunday break end">
                        </div>
                        <small>Inside the Sunday clock. Leave blank for no break.</small>
                    </div>
                    <p class="et-off" data-sunday-off @if($worksSunday) hidden @endif>Sunday is off. This person is paid that day. No punch and no cutoff.</p>
                    @error('sunday_start')<p class="et-error">{{ $message }}</p>@enderror
                    @error('sunday_end')<p class="et-error">{{ $message }}</p>@enderror
                </div>
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
                    <span>Shift</span>
                    <b><span class="et-badge {{ $kind === 'night' ? 'is-night' : 'is-day' }}">{{ $kind === 'night' ? 'Night' : 'Day' }}</span></b>
                </div>
                <div>
                    <span>Monday to Saturday</span>
                    <b>{{ $times['weekday_start'] }}–{{ $times['weekday_end'] }}@if(($times['weekday_break_start'] ?? '') !== '')<small>Break {{ $times['weekday_break_start'] }}–{{ $times['weekday_break_end'] }}</small>@endif</b>
                </div>
                <div>
                    <span>Sunday</span>
                    <b>{{ $times['works_sunday'] ? $times['sunday_start'].'–'.$times['sunday_end'] : 'Off · paid, no cutoff' }}@if($times['works_sunday'] && ($times['sunday_break_start'] ?? '') !== '')<small>Break {{ $times['sunday_break_start'] }}–{{ $times['sunday_break_end'] }}</small>@endif</b>
                </div>
            </div>
        @endif
    </div>
</section>
