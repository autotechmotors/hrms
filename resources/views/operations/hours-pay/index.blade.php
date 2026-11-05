@extends('layouts.app')
@section('title', 'Hours & pay')
@section('content')
    <div class="ph">
        <div>
            <h1>Hours & pay</h1>
            <p>Set a department’s day length, then keep it for a person or give them their own. Salary is set per person.</p>
        </div>
    </div>

    <div class="hp">
        <div class="hp-steps">
            <article class="card hp-step"><span>1</span><b>Department hours</b><p>One day length for everyone in that department.</p></article>
            <article class="card hp-step"><span>2</span><b>A person’s hours</b><p>They can use the department, or their own hours.</p></article>
            <article class="card hp-step"><span>3</span><b>Monthly salary</b><p>Set the amount payroll should use for that person.</p></article>
        </div>

        <section class="card ef-sec">
            <header>
                <div>
                    <h3>Departments</h3>
                    <p>These hours apply until a person has their own.</p>
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
                                        <small>{{ $department['code'] }} · {{ $department['people'] }} {{ $department['people'] === 1 ? 'person' : 'people' }}</small>
                                    </div>
                                    <strong data-hours>{{ $department['hours'] }}</strong>
                                </div>
                                @if($canEdit)
                                    <form class="hp-dept-form" method="post" action="{{ route('hours-pay.departments.store', $department['id']) }}">
                                        @csrf
                                        <label>
                                            <span>Hours a day</span>
                                            <input class="form-control" type="number" name="hours_per_day" min="0.5" max="24" step="0.5" inputmode="decimal" value="{{ $department['value'] }}" required>
                                        </label>
                                        <button class="btn pr sm" type="submit">Save</button>
                                    </form>
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
                    <h3>People</h3>
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
})(jQuery);
</script>
@endpush
