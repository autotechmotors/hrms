@extends('layouts.guest')
@section('title', 'Sign in')
@section('heading', 'Staff sign in')
@section('content')
    <form method="post" action="{{ route('login') }}">
        @csrf
        <x-text-field name="email" label="Email" type="email" required autofocus />
        <x-text-field name="password" label="Password" type="password" required />
        <button class="btn pr w-100" type="submit">Sign in</button>
    </form>
    <button class="btn pwa-guest" type="button" data-pwa-install hidden>
        <img src="{{ asset('icons/icon-192.png') }}" alt="" width="18" height="18">
        <span>Install app</span>
    </button>
@endsection
