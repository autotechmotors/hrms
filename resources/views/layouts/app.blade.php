<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.pwa-head')
    @php $pageTitle = trim($__env->yieldContent('title', 'Dashboard')); @endphp
    <title>{{ __($pageTitle) }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&family=Noto+Sans+Gujarati:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/flatpickr/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=163">
    @stack('styles')
    <script>
        (function () {
            var theme = localStorage.getItem('hrms-theme');
            if (theme === 'dark' || theme === 'light') {
                document.documentElement.setAttribute('data-theme', theme);
            }
        })();
    </script>
</head>
<body class="@yield('body-class')">
    <div class="glass-wave" aria-hidden="true"></div>
    @php
        $flashToasts = collect([
            session('success') ? ['type' => 'ok', 'message' => __((string) session('success'))] : null,
            session('status') ? ['type' => 'ok', 'message' => __((string) session('status'))] : null,
            session('error') ? ['type' => 'er', 'message' => __((string) session('error'))] : null,
            session('warning') ? ['type' => 'wn', 'message' => __((string) session('warning'))] : null,
            $errors->any() ? ['type' => 'er', 'message' => $errors->first()] : null,
        ])->filter()->values();
    @endphp
    <aside class="sb">
        @include('partials.nav')
    </aside>
    <div class="mn">
        <header class="tb">
            <button class="ib" type="button" id="mb" aria-label="{{ __('Menu') }}"><x-i name="menu" :size="18" /></button>
            <a class="tb-logo" href="{{ route('dashboard') }}">
                <img class="brand-icon" src="{{ asset('icons/icon-192.png') }}" alt="" width="42" height="42">
                <span>{{ config('app.name') }}</span>
            </a>
            <div class="crumb">{{ config('app.name') }} / <b>{{ __($pageTitle) }}</b></div>
            <div class="sp"></div>
            @include('partials.lang-switch')
            {{-- White / Dark stays available. Small screens hide this control in CSS; the buttons are not removed. --}}
          {{-- Work, then break. Sunday uses the switch. Night uses this person’s Sunday night. --}}net
            @php $unreadNotifications = auth()->user()->unreadNotifications()->count(); @endphp
            <a class="ib tb-bell" href="{{ route('notifications.index', [], false) }}" aria-label="{{ __('Notifications') }}">
                <x-i name="bell" :size="18" />
                <span class="tb-bell-count {{ $unreadNotifications ? 'show' : '' }}" data-notif-count>{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
            </a>
            <div class="sr dw" id="gs">
                <span><x-i name="search" /></span>
                <input id="q" placeholder="{{ __('Search pages') }}" autocomplete="off">
                <div class="dm" id="sres"></div>
            </div>
            @php
                $bits = preg_split('/\s+/', trim(auth()->user()->name)) ?: [];
                $initials = strtoupper(collect($bits)->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join(''));
                $hue = abs(crc32(auth()->user()->name)) % 360;
            @endphp
            <a class="tb-avatar" href="{{ route('profile.edit') }}" aria-label="{{ __('Profile') }}">
                <span class="av" style="--h:{{ $hue }};width:42px;height:42px">{{ $initials }}</span>
            </a>
            <div class="dw" id="usd">
                <button class="ib" type="button" data-dd="usd" style="width:auto;gap:6px" aria-label="{{ __('Profile') }}">
                    <span class="av" style="--h:{{ $hue }};width:30px;height:30px">{{ $initials }}</span>
                </button>
                <div class="dm">
                    <div class="nt"><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->email }}</small></div>
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button type="submit">{{ __('Sign out') }}</button>
                    </form>
                </div>
            </div>
        </header>
        <main class="pg">
            @yield('content')
        </main>
        <footer class="app-foot">{{ config('app.name') }}</footer>
    </div>
    @php $phoneNav = \App\Support\Navigation::phoneBar(auth()->user()); @endphp
    <nav class="tabbar" aria-label="Primary">
        @foreach($phoneNav['primary'] as $link)
            <a class="{{ request()->routeIs(...$link['active']) ? 'on' : '' }}" href="{{ route($link['route']) }}"><x-i :name="$link['icon']" :size="18" /><span>{{ __($link['label']) }}</span></a>
        @endforeach
            <a class="{{ request()->routeIs('more', 'profile.edit', 'notifications.*') ? 'on' : '' }}" href="{{ route('more') }}"><x-i name="menu" :size="18" /><span>{{ __('More') }}</span></a>
    </nav>

    @include('partials.pwa-install')
    <div class="toast-host" id="toastHost" aria-live="polite" aria-relevant="additions"></div>
    @php
        $uiText = [
            'continue' => __('Continue'),
            'confirm' => __('Please confirm'),
            'delete' => __('Delete'),
            'deleteName' => __('Delete :name?'),
            'none' => __('No results'),
            'range' => __('Showing :from–:to of :total'),
            'pageFail' => __('Could not load the next page.'),
        ];
    @endphp
    <script>
        window.__TOASTS__ = @json($flashToasts);
        window.__I18N = @json($uiText);
    </script>

    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Please confirm') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body" id="confirmText" data-empty="{{ __('Please confirm') }}"></div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn pr" id="confirmButton" data-default="{{ __('Continue') }}">{{ __('Continue') }}</button>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}?v=32"></script>
    @stack('scripts')
</body>
</html>
