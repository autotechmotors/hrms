@extends('layouts.app')
@section('title', 'Salary sheets')
@section('content')
    <div class="ph">
        <div>
            <div class="sg-title">
                <h1>Salary sheets</h1>
                <div class="sg-help" id="sgHelp">
                    <button class="sg-info" id="sgInfo" type="button" aria-label="How a salary sheet works" aria-expanded="false" aria-controls="sgPop">
                        <x-i name="help" :size="15" />
                    </button>
                    <div class="sg-pop" id="sgPop" hidden>
                        <ol class="sg-steps">
                            <li><b>1</b><span>Set hours and monthly salary</span></li>
                            <li><b>2</b><span>Pick dates, department, people</span></li>
                            <li><b>3</b><span>Generate the sheet</span></li>
                            <li><b>4</b><span>Excel, PDF, and history. The accountant approves, or rejects with a reason.</span></li>
                        </ol>
                    </div>
                </div>
            </div>
            <p>Choose the dates, one department, and the people. Pay follows the hours worked, then unpaid leave and advances in those dates come off.</p>
        </div>
        <a class="btn" href="{{ route('hours-pay.index') }}">Hours &amp; pay</a>
    </div>

    @include('operations.errors')

    @if($canGenerate)
        <section class="card sg-card">
            <header>
                <div>
                    <h3>Generate a sheet</h3>
                    <p>Generating sends this salary to the accountant. They approve it, or reject it with a reason.</p>
                </div>
            </header>
            <form class="sg-form" method="post" action="{{ route('salary-sheets.store') }}">
                @csrf
                <div class="sg-filters">
                    <label>From
                        <input class="in" type="date" name="starts_on" value="{{ old('starts_on', $starts) }}" required>
                    </label>
                    <label>To
                        <input class="in" type="date" name="ends_on" value="{{ old('ends_on', $ends) }}" required>
                    </label>
                    <label>Department
                        <select class="in" name="department_id" id="sgDept" required>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((int) old('department_id', $selected) === (int) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="sg-actions">
                        <button class="btn pr" type="submit">Generate salary</button>
                    </div>
                </div>
                <div class="sg-people-wrap" id="sgPeople">
                    @include('operations.salary-sheets._people')
                </div>
            </form>
        </section>
    @endif

    <section class="card sg-card">
        <header class="sg-hist-head">
            <div>
                <h3>Requests</h3>
                <p>Pending requests are waiting for the accountant. Super Admin and Admin can approve or reject them too.</p>
            </div>
            <div class="sg-hist-tools">
                <label class="sg-search">Status
                    <select class="in" id="sgStatus">
                        <option value="">All</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                    </select>
                </label>
                <label class="sg-search">
                    <span>Search</span>
                    <input class="in" id="sgQ" type="search" placeholder="Name or code" value="{{ request('q') }}">
                </label>
            </div>
        </header>
        <div id="sgHistory">
            @include('operations.salary-sheets._history')
        </div>
    </section>
    @include('operations.salary-sheets._reject')
@endsection

@push('scripts')
<script>
(function ($) {
    var historyUrl = @json(route('salary-sheets.index'));
    var peopleUrl = @json(route('salary-sheets.people'));
    var current = null;

    function loadHistory(page) {
        var req = $.ajax({
            url: historyUrl,
            data: { partial: 1, q: $('#sgQ').val(), status: $('#sgStatus').val(), page: page || 1 },
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        current = req;
        req.done(function (html) {
            if (req !== current) return;
            $('#sgHistory').html(html);
        });
    }

    $('#sgDept').on('change', function () {
        $('#sgPeople').addClass('is-loading');
        $.get(peopleUrl, { department_id: this.value }, function (html) {
            $('#sgPeople').html(html).removeClass('is-loading');
        });
    });

    function paintCount() {
        var boxes = $('#sgPeople .sg-pick');
        var on = boxes.filter(':checked').length;
        $('#sgCount').text(on + ' selected');
        var all = document.getElementById('sgAll');
        if (!all) return;
        all.checked = boxes.length > 0 && on === boxes.length;
        all.indeterminate = on > 0 && on < boxes.length;
    }

    $(document).on('change', '#sgAll', function () {
        $('#sgPeople .sg-pick').prop('checked', this.checked);
        paintCount();
    });

    $(document).on('change', '#sgPeople .sg-pick', paintCount);

    $(document).on('input', '#sgFind', function () {
        var q = $(this).val().toLowerCase();
        $('#sgPeople tbody tr').each(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
        });
    });

    window.sgReloadHistory = function () { loadHistory(1); };

    var timer = null;
    $('#sgQ').on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { loadHistory(1); }, 280);
    });
    $('#sgStatus').on('change', function () { loadHistory(1); });

    var pinned = false;
    var infoBtn = document.getElementById('sgInfo');
    var infoPop = document.getElementById('sgPop');
    var infoWrap = document.getElementById('sgHelp');
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

    $('#sgHistory').on('click', '.pgn a', function (event) {
        event.preventDefault();
        var page = new URL(this.href, window.location.origin).searchParams.get('page') || 1;
        loadHistory(page);
    });
})(jQuery);
</script>
@include('operations.salary-sheets._decide-script')
@endpush
