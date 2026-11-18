@extends('layouts.guest')
@section('title', 'Forgot password')
@section('heading', 'Forgot password')
@section('lead', 'Enter the email on your staff account. We will send a link to choose a new password.')
@section('content')
    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <x-text-field name="email" label="Email" type="email" required autofocus />
        <button class="btn pr w-100" type="submit">Send reset link</button>
    </form>
    <a class="guest-link" href="{{ route('login') }}">Back to sign in</a>
@endsection
