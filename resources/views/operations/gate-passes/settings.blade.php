@extends('layouts.app')
@section('title', 'Gate Pass rules')
@section('content')
    <div class="ph">
        <div>
            <h1>Gate Pass rules</h1>
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
@endsection
