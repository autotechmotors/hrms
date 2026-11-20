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
                <h1>{{ __('More') }}</h1>
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
                <span>{{ __('Edit profile') }}</span>
            </a>
        </section>

        <button class="more-card more-install" type="button" data-pwa-install hidden>
            <img src="{{ asset('icons/icon-192.png') }}" alt="" width="40" height="40">
            <span class="more-label">
                <b>{{ __('Install app') }}</b>
                <small>{{ __('Add the HRMS icon to your home screen') }}</small>
            </span>
            <x-i class="more-go" name="right" :size="16" />
        </button>

        <section class="more-card">
            <h2>{{ __('Language') }}</h2>
            <a class="more-row {{ app()->getLocale() === 'en' ? 'on' : '' }}" href="{{ route('locale.update', 'en') }}" hreflang="en" lang="en"><span class="more-label">English</span></a>
            <a class="more-row {{ app()->getLocale() === 'hi' ? 'on' : '' }}" href="{{ route('locale.update', 'hi') }}" hreflang="hi" lang="hi"><span class="more-label">हिन्दी</span></a>
            <a class="more-row {{ app()->getLocale() === 'gu' ? 'on' : '' }}" href="{{ route('locale.update', 'gu') }}" hreflang="gu" lang="gu"><span class="more-label">ગુજરાતી</span></a>
        </section>

        <section class="more-card">
            <h2>{{ __('Inbox') }}</h2>
            <a class="more-row {{ request()->routeIs('notifications.*') ? 'on' : '' }}" href="{{ route('notifications.index', [], false) }}">
                <span class="more-ico"><x-i name="bell" :size="18" /></span>
                <span class="more-label">{{ __('Notifications') }}</span>
                @if(($unreadNotifications ?? 0) > 0)
                    <span class="more-count">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                @endif
                <x-i class="more-go" name="right" :size="16" />
            </a>
        </section>

        @foreach($groups as $label => $links)
            <section class="more-card">
                <h2>{{ __($label) }}</h2>
                @foreach($links as $link)
                    <a class="more-row {{ request()->routeIs(...$link['active']) ? 'on' : '' }}" href="{{ route($link['route']) }}">
                        <span class="more-ico"><x-i :name="$link['icon']" :size="18" /></span>
                        <span class="more-label">{{ __($link['label']) }}</span>
                        <x-i class="more-go" name="right" :size="16" />
                    </a>
                @endforeach
            </section>
        @endforeach

        <form class="more-card more-logout" method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit">
                <span class="more-ico"><x-i name="out" :size="18" /></span>
                <span class="more-label">{{ __('Logout') }}</span>
            </button>
        </form>
    </div>
@endsection
