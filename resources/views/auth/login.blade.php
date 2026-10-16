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
@endsection
