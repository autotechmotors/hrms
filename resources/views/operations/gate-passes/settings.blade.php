@extends('layouts.app')
@section('title', 'Gate Pass rules')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Gate Pass rules') }}</h1>
            <p>These flags change approval the same way as GMS settings.</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ route('gate-passes.index') }}">Back</a>
        </div>
    </div>

    @include('operations.errors')

    <section class="card">
        <form class="cb" method="post" action="{{ route('gate-passes.settings.update') }}">
            @csrf
            @method('put')
            <label class="lv-check">
                <input type="checkbox" name="allow_self_apply" value="1" @checked(old('allow_self_apply', $settings->allow_self_apply))>
                <span>Allow self apply — employee with a login can request their own pass</span>
            </label>
            <label class="lv-check">
                <input type="checkbox" name="require_parent_approval" value="1" @checked(old('require_parent_approval', $settings->require_parent_approval))>
                <span>Require parent approval — child self-apply stays pending</span>
            </label>
            <label class="lv-check">
                <input type="checkbox" name="prevent_duplicate_active" value="1" @checked(old('prevent_duplicate_active', $settings->prevent_duplicate_active))>
                <span>Prevent duplicate active pass</span>
            </label>
            <div class="lv-modal-grid" style="margin-top:14px">
                <div>
                    <label class="form-label">Max duration (minutes)</label>
                    <input class="in w-100" type="number" min="15" max="1440" name="max_duration_minutes" required value="{{ old('max_duration_minutes', $settings->max_duration_minutes) }}">
                </div>
                <div>
                    <label class="form-label">Default duration (minutes)</label>
                    <input class="in w-100" type="number" min="15" max="1440" name="default_duration_minutes" required value="{{ old('default_duration_minutes', $settings->default_duration_minutes) }}">
                </div>
            </div>
            <div class="more-save" style="margin-top:16px">
                <button class="btn pr" type="submit">Save rules</button>
            </div>
        </form>
    </section>

    <section class="card" style="margin-top:16px">
        <div class="cb">
            <h3>{{ __('How OUT and hours work') }}</h3>
            <ul class="st-list">
                <li>Any role can request a pass. Parent approval opens the gate OUT machine. Reject leaves the machine closed and notifies the employee with the reason.</li>
                <li>After an approved OUT punch, time away is not desk work until they return.</li>
                <li>Personal work: actual minutes out are cut from that day’s working hours. Coming back 1 minute early keeps that leftover minute as work.</li>
                <li>Office work: authorized outing minutes count as working hours.</li>
                <li>If a 1-hour pass expires and they are still out, clock-in starts from the next punch. No return that day is half-day early leave.</li>
                <li>Mark a terminal as <b>Gate OUT (pass only)</b> on Devices. People are pushed there only after approval and removed on reject or expire.</li>
            </ul>
        </div>
    </section>
@endsection
