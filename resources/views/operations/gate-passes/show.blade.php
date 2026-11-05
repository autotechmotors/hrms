@extends('layouts.app')
@section('title', $gatePass->number)
@section('content')
    @include('operations.errors')

    <div class="ph">
        <div>
            <x-back :href="route('gate-passes.index')" label="Gate Pass" />
            <h1>{{ $gatePass->number }}</h1>
            <p>Digital Gate Pass · {{ $gatePass->formatted_date }} · {{ $gatePass->employee?->full_name }}</p>
        </div>
    </div>

    <div class="gp-shell">
        @include('operations.gate-passes._digital-pass')
    </div>
@endsection
