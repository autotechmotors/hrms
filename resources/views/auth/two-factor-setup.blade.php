@extends('layouts.guest')
@section('title', 'Set up authenticator')
@section('heading', 'Set up your authenticator')
@section('content')
    <p class="small text-secondary">Scan this code with an authenticator app, or enter the secret manually. This account cannot continue until the code is confirmed.</p>
    <div class="mb-3">{!! $qr !!}</div>
    <p class="font-monospace small">{{ $secret }}</p>
    <form method="post" action="{{ route('two-factor.setup') }}">
        @csrf
        <x-text-field name="code" label="6-digit code" required autocomplete="one-time-code" />
        <button class="btn pr w-100" type="submit">Confirm</button>
    </form>
@endsection
