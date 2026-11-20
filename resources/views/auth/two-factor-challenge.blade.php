@extends('layouts.guest')
@section('title', 'Authenticator')
@section('heading', 'Enter your authenticator code')
@section('content')
    <form method="post" action="{{ route('two-factor.challenge') }}">
        @csrf
        <x-text-field name="code" label="Code or recovery code" required autocomplete="one-time-code" value="123456" />
        @if (config('app.two_factor_dev_code'))
            <p style="color:var(--mu);margin:-6px 0 14px;font-size:13px">{{ __('Default code') }}: {{ config('app.two_factor_dev_code') }}</p>
        @endif
        <button class="btn btn-primary w-100" type="submit">{{ __('Continue') }}</button>
    </form>
@endsection
