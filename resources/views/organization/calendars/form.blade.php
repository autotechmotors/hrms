@extends('layouts.app')
@section('title', 'Work calendar')
@section('content')
    @php $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; @endphp
    <h1 class="h3 mb-3">{{ $calendar ? 'Edit work calendar' : 'New work calendar' }}</h1>
    <form class="card card-body" method="post" action="{{ $calendar ? route('work-calendars.update', $calendar) : route('work-calendars.store') }}">
        @csrf @if($calendar) @method('put') @endif
        @include('partials.workspace-ids', ['companyId' => $calendar->company_id ?? null])
        <x-text-field name="name" label="Name" :value="$calendar->name ?? ''" required />
        <x-text-field name="effective_from" label="Effective from" type="date" :value="optional($calendar?->effective_from)->toDateString()" required />
        <x-text-field name="effective_to" label="Effective to" type="date" :value="optional($calendar?->effective_to)->toDateString()" />
        <div class="mb-3">
            <div class="form-label">Working days</div>
            @foreach($days as $number => $label)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="working_days[]" value="{{ $number }}" id="day{{ $number }}" @checked(in_array($number, old('working_days', $workingDays), true))>
                    <label class="form-check-label" for="day{{ $number }}">{{ $label }}</label>
                </div>
            @endforeach
            @error('working_days')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <x-check-field name="is_active" label="Active" :checked="$calendar->is_active ?? true" />
        <button class="btn btn-primary">Save</button>
    </form>
@endsection
