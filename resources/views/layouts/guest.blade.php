<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('partials.pwa-head')
    @php
        $pageTitle = trim($__env->yieldContent('title', 'Sign in'));
        $pageHeading = trim($__env->yieldContent('heading', 'Sign in'));
        $leadDefault = 'Sign in with the role account you were given. An employee account sees only that person\'s own records.';
        $pageLead = trim($__env->yieldContent('lead'));
        if ($pageLead === '') {
            $pageLead = $leadDefault;
        }
    @endphp
    <title>{{ __($pageTitle) }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&family=Noto+Sans+Gujarati:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/flatpickr/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=139">
</head>
<body class="guest-body">
    <div class="auth-stage" aria-hidden="true">
        <div class="auth-sky"></div>
    </div>
    <div class="guest-shell auth-split">
        <aside class="auth-visual" aria-hidden="true">
            <img class="auth-scene" src="{{ asset('assets/images/auth-plant.jpg') }}" alt="">
        </aside>
        <section class="guest-panel">
            <div class="guest-card">
                <div class="guest-brand">
                    <img class="brand-icon" src="{{ asset('icons/icon-192.png') }}" alt="" width="44" height="44">
                    <span class="lb">{{ config('app.name') }}</span>
                    @include('partials.lang-switch')
                </div>
                <h1>{{ __($pageHeading) }}</h1>
                <p class="guest-lead">{{ __($pageLead) }}</p>
                @if (session('status'))
                    <div class="alert alert-success">{{ __((string) session('status')) }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ __((string) session('error')) }}</div>
                @endif
                @yield('content')
            </div>
        </section>
    </div>
    @include('partials.pwa-install')
    <script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}?v=26"></script>
</body>
</html>
