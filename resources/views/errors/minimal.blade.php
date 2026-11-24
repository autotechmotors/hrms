@php
    \App\Http\Middleware\SetLocale::apply(request());
    $status = (int) trim($__env->yieldContent('code'));
    if ($status < 100 && isset($exception) && method_exists($exception, 'getStatusCode')) {
        $status = (int) $exception->getStatusCode();
    }
    if ($status < 100) {
        $status = 500;
    }
    $copy = \App\Support\HttpErrorCopy::forStatus($status);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('partials.pwa-head')
    <title>{{ $status }} — {{ __($copy['title']) }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&family=Noto+Sans+Gujarati:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=149">
</head>
<body class="guest-body">
    <div class="auth-stage" aria-hidden="true">
        <div class="auth-sky"></div>
    </div>
    <div class="guest-shell">
        <section class="guest-panel">
            <div class="guest-card err-card">
                <div class="guest-brand">
                    <img class="brand-icon" src="{{ asset('icons/icon-192.png') }}" alt="" width="44" height="44">
                    <span class="lb">{{ config('app.name') }}</span>
                    <nav class="lang-set" aria-label="{{ __('Language') }}">
                        @foreach (['en' => 'EN', 'hi' => 'हि', 'gu' => 'ગુ'] as $code => $short)
                            <a href="{{ url('/locale/'.$code) }}" hreflang="{{ $code }}" lang="{{ $code }}" class="{{ app()->getLocale() === $code ? 'on' : '' }}">{{ $short }}</a>
                        @endforeach
                    </nav>
                </div>
                <p class="err-code">{{ $status }}</p>
                <h1>{{ __($copy['title']) }}</h1>
                <p class="guest-lead">{{ __($copy['message']) }}</p>
                <div class="err-actions">
                    <a class="btn pr" href="{{ url('/') }}">{{ __('Go home') }}</a>
                    <button type="button" class="btn err-back" onclick="history.back()">{{ __('Back') }}</button>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
