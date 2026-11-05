@extends('layouts.app')
@section('title', 'More')
@section('content')
    @php
        $bits = preg_split('/\s+/', trim(auth()->user()->name)) ?: [];
        $initials = strtoupper(collect($bits)->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join(''));
    @endphp
    <div class="more-page">
        <div class="ph">
            <div>
                <h1>More</h1>
            </div>
        </div>

        <section class="more-hero">
            <div class="more-who">
                <span class="av more-av">{{ $initials }}</span>
                <span>
                    <b>{{ auth()->user()->name }}</b>
                    <small>{{ $roleLabel }}</small>
                </span>
            </div>
            <a class="more-edit" href="{{ route('profile.edit') }}">
                <x-i name="pen" :size="15" />
                <span>Edit profile</span>
            </a>
        </section>

        @foreach($groups as $label => $links)
            <section class="more-card">
                <h2>{{ $label }}</h2>
                @foreach($links as $link)
                    <a class="more-row {{ request()->routeIs(...$link['active']) ? 'on' : '' }}" href="{{ route($link['route']) }}">
                        <span class="more-ico"><x-i :name="$link['icon']" :size="18" /></span>
                        <span class="more-label">{{ $link['label'] }}</span>
                        <x-i class="more-go" name="right" :size="16" />
                    </a>
                @endforeach
            </section>
        @endforeach

        <form class="more-card more-logout" method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit">
                <span class="more-ico"><x-i name="out" :size="18" /></span>
                <span class="more-label">Logout</span>
            </button>
        </form>
    </div>
@endsection
