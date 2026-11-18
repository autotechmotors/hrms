@extends('layouts.guest')
@section('title', 'New password')
@section('heading', 'Choose a new password')
@section('lead', 'Set the password you will use to sign in. This link works once, for 60 minutes.')
@section('content')
    <form method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-text-field name="email" label="Email" type="email" :value="$email" required autofocus />
        <x-text-field name="password" label="New password" type="password" required autocomplete="new-password" />
        <x-text-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        <p class="form-text" style="margin-top:-8px">At least 8 characters, with upper and lower case letters and a number.</p>
        <button class="btn pr w-100" type="submit">Save password</button>
    </form>
    <a class="guest-link" href="{{ route('login') }}">Back to sign in</a>
@endsection
