@extends('layouts.app')
@section('title', 'Hours & pay')
@section('content')
    <div class="ph">
        <div>
            <div class="sg-title">
                <h1>{{ __('Hours & pay') }}</h1>
                <div class="sg-help" id="hpHelp">
                    <button class="sg-info" id="hpInfo" type="button" aria-label="How hours and pay work" aria-expanded="false" aria-controls="hpPop">
                        <x-i name="help" :size="15" />
                    </button>
                    <div class="sg-pop" id="hpPop" hidden>
                        <ol class="sg-steps">
                            <li><b>1</b><span>Department times. Day has Monday to Saturday and Sunday. Night has Monday to Saturday, and a Sunday night switch for that department only.</span></li>
                            <li><b>2</b><span>A person’s times. They can use the department clocks, or their own Monday to Saturday clocks. Sunday night stays the department switch.</span></li>
                            <li><b>3</b><span>Monthly salary. Set the amount payroll should use for that person.</span></li>
                        </ol>
                    </div>
                </div>
            </div>
            <p>Set the Monday to Saturday clock and the Sunday clock. A person can keep the department times or use their own. Salary stays per person.</p>
        </div>
    </div>

    <div class="hp">
        <section class="card ef-sec">
            <header>
                <div>
                    <h3>{{ __('Departments') }}</h3>
                    <p>These clocks apply until a person has their own. Sunday night is on or off for the whole department.</p>
                </div>
            </header>
            <div class="cb">
                @if($departments->isEmpty())
                    <p class="ed-empty">No departments in your scope.</p>
                @else
                    <div class="hp-depts">
                        @foreach($departments as $department)
                            <article class="hp-dept" data-department="{{ $department['id'] }}">
                                <div class="hp-dept-top">
                                    <div>
                                        <b>{{ $department['name'] }}</b>
                                        <small>{{ $department['code'] }}</small>
                                    </div>
                                    <span class="hp-count">{{ $department['people'] }} {{ $department['people'] === 1 ? 'person' : 'people' }}</span>
                                </div>
                                @if($canEdit)
                                    <form class="hp-dept-form" method="post" action="{{ route('hours-pay.departments.store', $department['id']) }}">
                                        @csrf
                                        <div class="hp-clocks">
                                            <div class="hp-clock">
                                                <span>Day · Mon–Sat</span>
                                                <div class="hp-line">
                                                    <b>{{ __('Work') }}</b>
                                                    <input class="form-control" type="time" name="weekday_start" value="{{ $department['weekday_start'] }}" required aria-label="Day start">
                                                    <em>to</em>
                                                    <input class="form-control" type="time" name="weekday_end" value="{{ $department['weekday_end'] }}" required aria-label="Day end">
                                                </div>
                                                <div class="hp-line">
                                                    <b>{{ __('Break') }}</b>
                                                    <input class="form-control" type="time" name="weekday_break_start" value="{{ $department['weekday_break_start'] }}" aria-label="Day break start">
                                                    <em>to</em>
                                                    <input class="form-control" type="time" name="weekday_break_end" value="{{ $department['weekday_break_end'] }}" aria-label="Day break end">
                                                </div>
                                            </div>
                                            <div class="hp-clock">
                                                <span>Day · Sunday</span>
                                                <div class="hp-line">
                                                    <b>{{ __('Work') }}</b>
                                                    <input class="form-control" type="time" name="sunday_start" value="{{ $department['sunday_start'] }}" required aria-label="Sunday start">
                                                    <em>to</em>
                                                    <input class="form-control" type="time" name="sunday_end" value="{{ $department['sunday_end'] }}" required aria-label="Sunday end">
                                                </div>
                                                <div class="hp-line">
                                                    <b>{{ __('Break') }}</b>
                                                    <input class="form-control" type="time" name="sunday_break_start" value="{{ $department['sunday_break_start'] }}" aria-label="Sunday break start">
                                                    <em>to</em>
                                                    <input class="form-control" type="time" name="sunday_break_end" value="{{ $department['sunday_break_end'] }}" aria-label="Sunday break end">
                                                </div>
                                            </div>
                                            <div class="hp-clock">
                                                <span>Night · Mon–Sat</span>
                                                <div class="hp-line">
                                                    <b>{{ __('Work') }}</b>
                                                    <input class="form-control" type="time" name="night_weekday_start" value="{{ $department['night_weekday_start'] }}" required aria-label="Night start">
                                                    <em>to</em>
                                                    <input class="form-control" type="time" name="night_weekday_end" value="{{ $department['night_weekday_end'] }}" required aria-label="Night end">
                                                </div>
                                                <div class="hp-line">
                                                    <b>{{ __('Break') }}</b>
                                                    <input class="form-control" type="time" name="night_weekday_break_start" value="{{ $department['night_weekday_break_start'] }}" aria-label="Night break start">
                                                    <em>to</em>
                                                    <input class="form-control" type="time" name="night_weekday_break_end" value="{{ $department['night_weekday_break_end'] }}" aria-label="Night break end">
                                                </div>
                                            </div>
                                            <div class="hp-clock">
                                                <div class="hp-sunday-top">
                                                    <span>Night · Sunday</span>
                                                    <input type="hidden" name="night_works_sunday" value="0">
                                                    <label class="et-switch">
                                                        <input type="checkbox" name="night_works_sunday" value="1" data-night-sunday @checked($department['night_works_sunday'])>
                                                        <span class="rl-ui" aria-hidden="true"></span>
                                                        <span class="et-switch-txt" data-night-sunday-label>{{ $department['night_works_sunday'] ? 'On' : 'Off' }}</span>
                                                    </label>
                                                </div>
                                                <p class="hp-night-note" data-night-sunday-off @if($department['night_works_sunday']) hidden @endif>Off. Paid Sunday, no punch and no cutoff.</p>
                                                <div class="hp-line" data-night-sunday-times @unless($department['night_works_sunday']) hidden @endunless>
                                                    <b>{{ __('Work') }}</b>
                                                    <input class="form-control" type="time" name="night_sunday_start" value="{{ $department['night_sunday_start'] }}" @disabled(! $department['night_works_sunday']) @required($department['night_works_sunday']) aria-label="Sunday night start">
                                                    <em>to</em>
                                                    <input class="form-control" type="time" name="night_sunday_end" value="{{ $department['night_sunday_end'] }}" @disabled(! $department['night_works_sunday']) @required($department['night_works_sunday']) aria-label="Sunday night end">
                                                </div>
                                                <div class="hp-line" data-night-sunday-times @unless($department['night_works_sunday']) hidden @endunless>
                                                    <b>{{ __('Break') }}</b>
                                                    <input class="form-control" type="time" name="night_sunday_break_start" value="{{ $department['night_sunday_break_start'] }}" @disabled(! $department['night_works_sunday']) @required($department['night_works_sunday']) aria-label="Sunday night break start">
                                                    <em>to</em>
                                                    <input class="form-control" type="time" name="night_sunday_break_end" value="{{ $department['night_sunday_break_end'] }}" @disabled(! $department['night_works_sunday']) @required($department['night_works_sunday']) aria-label="Sunday night break end">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="hp-dept-foot">
                                            <p data-hours>{{ $department['hours'] }}</p>
                                            <button class="btn pr sm" type="submit">{{ __('Save') }}</button>
                                        </div>
                                    </form>
                                @else
                                    <p class="hp-dept-foot" data-hours>{{ $department['hours'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="card ef-sec">
            <header>
                <div>
                    <h3>{{ __('People') }}</h3>
                    <p>Open a person to set their hours and salary.</p>
                </div>
            </header>
            <div class="cb">
                <form id="hpFind" class="hp-find">
                    <label class="hp-search">
                        <span class="visually-hidden">Search people</span>
                        <input class="form-control" id="hpQ" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search name or code">
                    </label>
                    <label>
                        <span class="visually-hidden">Department</span>
                        <select class="form-select" name="department_id" data-native>
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department['id'] }}" @selected($filters['department_id'] === (string) $department['id'])>{{ $department['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
                <div id="hpPeople">
                    @include('operations.hours-pay._people')
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
(function ($) {
    const find = document.getElementById('hpFind');
    const people = document.getElementById('hpPeople');
    if (!find || !people) return;
    const url = @json(route('hours-pay.index'));
    let req = null;
    let timer = null;

    function query(extra) {
        const data = $(find).serializeArray().filter((item) => String(item.value || '').trim() !== '');
        if (extra) Object.keys(extra).forEach((key) => data.push({ name: key, value: extra[key] }));
        return $.param(data);
    }

    function load(extra) {
        if (req) req.abort();
        const next = url + (query(extra) ? '?' + query(extra) : '');
        req = $.ajax({
            url: next,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { partial: 1 }
        }).done(function (html) {
            people.innerHTML = html;
            if (window.history && window.history.replaceState) window.history.replaceState({}, '', next);
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') return;
            window.toast('Could not refresh the list', 'er');
        }).always(function () { req = null; });
    }

    find.addEventListener('input', function (event) {
        if (event.target.name !== 'q') return;
        clearTimeout(timer);
        timer = setTimeout(() => load(), 300);
    });
    find.addEventListener('change', function (event) {
        if (event.target.name === 'department_id') load();
    });
    people.addEventListener('click', function (event) {
        const link = event.target.closest('.hp-pager a');
        if (!link) return;
        event.preventDefault();
        const page = new URL(link.href, window.location.origin).searchParams.get('page');
        load(page ? { page: page } : null);
    });

    function paintNightSunday(form) {
        const box = form.querySelector('[data-night-sunday]');
        if (!box) return;
        const on = box.checked;
        form.querySelectorAll('[data-night-sunday-times]').forEach((el) => { el.hidden = !on; });
        form.querySelectorAll('[data-night-sunday-off]').forEach((el) => { el.hidden = on; });
        form.querySelectorAll('[data-night-sunday-times] input').forEach((input) => {
            input.disabled = !on;
            input.required = on;
        });
        const label = form.querySelector('[data-night-sunday-label]');
        if (label) label.textContent = on ? 'On' : 'Off';
    }
    document.querySelectorAll('.hp-dept-form').forEach(paintNightSunday);
    document.addEventListener('change', function (event) {
        if (!event.target.matches('[data-night-sunday]')) return;
        const form = event.target.closest('.hp-dept-form');
        if (form) paintNightSunday(form);
    });

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('.hp-dept-form');
        if (!form) return;
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        $.ajax({
            url: form.action,
            method: 'POST',
            data: $(form).serialize(),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (body) {
            const card = form.closest('.hp-dept');
            const label = card.querySelector('[data-hours]');
            if (label) label.textContent = body.hours;
            window.toast(body.message);
            load();
        }).fail(function (xhr) {
            const errors = xhr.responseJSON && xhr.responseJSON.errors;
            const message = errors ? Object.values(errors)[0][0] : 'Could not save these hours';
            window.toast(message, 'er');
        }).always(function () { button.disabled = false; });
    });

    var pinned = false;
    var infoBtn = document.getElementById('hpInfo');
    var infoPop = document.getElementById('hpPop');
    var infoWrap = document.getElementById('hpHelp');
    function showInfo() {
        infoPop.hidden = false;
        infoBtn.classList.add('is-open');
    }
    function hideInfo() {
        if (pinned) return;
        infoPop.hidden = true;
        infoBtn.classList.remove('is-open');
        infoBtn.setAttribute('aria-expanded', 'false');
    }
    infoWrap.addEventListener('mouseenter', showInfo);
    infoWrap.addEventListener('mouseleave', hideInfo);
    infoBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        pinned = !pinned;
        if (pinned) {
            showInfo();
            infoBtn.setAttribute('aria-expanded', 'true');
            return;
        }
        infoPop.hidden = true;
        infoBtn.classList.remove('is-open');
        infoBtn.setAttribute('aria-expanded', 'false');
    });
    infoPop.addEventListener('click', function (event) { event.stopPropagation(); });
    document.addEventListener('click', function () {
        if (!pinned) return;
        pinned = false;
        hideInfo();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || infoPop.hidden) return;
        pinned = false;
        hideInfo();
    });
})(jQuery);
</script>
@endpush
