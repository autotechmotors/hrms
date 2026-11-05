@extends('layouts.app')
@section('title', 'New Gate Pass')
@section('content')
    <div class="ph">
        <div>
            <x-back :href="route('gate-passes.index')" label="Gate Pass" />
            <h1>New Gate Pass</h1>
            <p>Date is today only. Choose work type, then coming back or early leave.</p>
        </div>
        <div class="ac">
            <button class="btn pr" type="submit" form="gatePassForm">Submit Gate Pass</button>
        </div>
    </div>

    @include('operations.errors')

    <form id="gatePassForm" class="ef gp-create" method="post" action="{{ route('gate-passes.store') }}">
        @csrf

        <div class="ef-grid">
            <div class="ef-main">
                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Employee</h3>
                            <p>Who is going out</p>
                        </div>
                    </header>
                    <div class="cb">
                        <x-select-field name="employee_id" label="Employee" required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id', $selfEmployee?->id) === (string) $employee->id)>
                                    {{ $employee->employee_code }} — {{ $employee->full_name }}
                                    @if($selfEmployee && (int) $selfEmployee->id === (int) $employee->id) (Self)@endif
                                </option>
                            @endforeach
                        </x-select-field>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>When</h3>
                            <p>Out time is required. Date stays today.</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="g c2">
                            <x-text-field name="date" label="Date" type="date" :value="old('date', now()->toDateString())" required readonly />
                            <x-text-field name="out_time" label="Out time" type="time" :value="old('out_time', now()->format('H:i'))" required />
                        </div>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Type</h3>
                            <p>Personal or office work, then return or early leave.</p>
                        </div>
                    </header>
                    <div class="cb">
                        <label class="form-label">Work type <span class="text-danger">*</span></label>
                        <div class="gp-leave-grid" id="workTypeGrid">
                            <label class="gp-leave-card @if(old('work_type', 'personal') === 'personal') on @endif">
                                <input type="radio" name="work_type" value="personal" @checked(old('work_type', 'personal') === 'personal')>
                                <b>Personal Work</b>
                                <small>Bank, hospital, home or private work.</small>
                            </label>
                            <label class="gp-leave-card @if(old('work_type') === 'office') on @endif">
                                <input type="radio" name="work_type" value="office" @checked(old('work_type') === 'office')>
                                <b>Office Work</b>
                                <small>Client visit, site, official duty.</small>
                            </label>
                        </div>

                        <label class="form-label">Return to office? <span class="text-danger">*</span></label>
                        <div class="gp-leave-grid" id="leaveTypeGrid">
                            <label class="gp-leave-card @if(old('leave_type', 'return') === 'return') on @endif">
                                <input type="radio" name="leave_type" value="return" @checked(old('leave_type', 'return') === 'return')>
                                <b>Coming back</b>
                                <small>Need duration. Max {{ intdiv($settings->max_duration_minutes, 60) }} hours.</small>
                            </label>
                            <label class="gp-leave-card @if(old('leave_type') === 'early_leave') on @endif">
                                <input type="radio" name="leave_type" value="early_leave" @checked(old('leave_type') === 'early_leave')>
                                <b>Early leave</b>
                                <small>Not returning today. Attendance note.</small>
                            </label>
                        </div>

                        <div id="durationWrap">
                            <x-text-field name="duration_time" label="Duration" type="time" :value="old('duration_time', $defaultDuration)" required />
                            <small class="gp-hint">Minimum 15 minutes. Hours and minutes only.</small>
                        </div>
                        <p class="gp-hint" id="earlyLeaveNote" hidden>Not returning today. Pass stays valid until end of day.</p>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Reason</h3>
                            <p>Why this Gate Pass is needed</p>
                        </div>
                    </header>
                    <div class="cb">
                        <x-text-field name="reason" label="Reason" type="textarea" rows="4" required placeholder="Why is this Gate Pass needed?" />
                    </div>
                </section>
            </div>
        </div>

        <div class="ef-bar">
            <a class="btn" href="{{ route('gate-passes.index') }}">Cancel</a>
            <button class="btn pr" type="submit">Submit Gate Pass</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const grid = document.getElementById('leaveTypeGrid');
    const workGrid = document.getElementById('workTypeGrid');
    const durationWrap = document.getElementById('durationWrap');
    const durationInput = durationWrap.querySelector('[name="duration_time"]');
    const note = document.getElementById('earlyLeaveNote');

    function mark(el) {
        el.querySelectorAll('.gp-leave-card').forEach(function (card) {
            card.classList.toggle('on', card.querySelector('input').checked);
        });
    }

    function sync() {
        const type = document.querySelector('input[name="leave_type"]:checked')?.value;
        const early = type === 'early_leave';
        durationWrap.hidden = early;
        if (durationInput) durationInput.required = !early;
        note.hidden = !early;
        mark(grid);
        mark(workGrid);
    }

    grid.addEventListener('change', sync);
    workGrid.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
