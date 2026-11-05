@extends('layouts.app')
@section('title', 'Edit profile')
@section('content')
    <div class="ph">
        <div>
            <x-back :href="route('more')" label="More" />
            <h1>Edit profile</h1>
            <p>Update your name, email, and password.</p>
        </div>
    </div>

    <section class="card">
        <form class="cb" method="post" action="{{ route('profile.update') }}">
            @csrf
            @method('put')
            <x-text-field name="name" label="Name" :value="$account->name" required />
            <x-text-field name="email" label="Email" type="email" :value="$account->email" required />
            <x-text-field name="password" label="New password" type="password" autocomplete="new-password" />
            <x-text-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
            <div class="more-save">
                <a class="btn" href="{{ route('more') }}">Cancel</a>
                <button class="btn pr" type="submit">Save profile</button>
            </div>
        </form>
    </section>
@endsection
