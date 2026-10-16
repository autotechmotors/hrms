@extends('layouts.guest')
@section('title', 'Authenticator')
@section('heading', 'Enter your authenticator code')
@section('content')
    <form method="post" action="{{ route('two-factor.challenge') }}">
        @csrf
        <x-text-field name="code" label="Code or recovery code" required autocomplete="one-time-code" />
        <button class="btn btn-primary w-100" type="submit">Continue</button>
    </form>
@endsection
