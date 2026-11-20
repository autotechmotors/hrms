@extends('layouts.guest')
@section('title', 'Sign in')
@section('heading', 'Staff sign in')
@section('content')
    <form method="post" action="{{ route('login') }}">
        @csrf
        <x-text-field name="email" label="Email" type="email" required autofocus />
        <x-text-field name="password" label="Password" type="password" required />
        <div class="guest-extra">
            <a class="guest-link" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
        </div>
        <button class="btn pr w-100" type="submit">{{ __('Sign in') }}</button>
    </form>
    <button class="btn pwa-guest" type="button" data-pwa-install hidden>
        <img src="{{ asset('icons/icon-192.png') }}" alt="" width="18" height="18">
        <span>{{ __('Install app') }}</span>
    </button>
@endsection
