<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/flatpickr/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=34">
</head>
<body>
    @php
        $flashToasts = collect([
            session('success') ? ['type' => 'ok', 'message' => session('success')] : null,
            session('status') ? ['type' => 'ok', 'message' => session('status')] : null,
            session('error') ? ['type' => 'er', 'message' => session('error')] : null,
            session('warning') ? ['type' => 'wn', 'message' => session('warning')] : null,
            $errors->any() ? ['type' => 'er', 'message' => $errors->first()] : null,
        ])->filter()->values();
    @endphp
    <aside class="sb">
        @include('partials.nav')
    </aside>
    <div class="mn">
        <header class="tb">
            <button class="ib" type="button" id="mb" aria-label="Toggle sidebar"><x-i name="menu" :size="18" /></button>
            <div class="crumb">{{ config('app.name') }} / <b>@yield('title', 'Dashboard')</b></div>
            <div class="sp"></div>
            <div class="sr dw" id="gs">
                <span><x-i name="search" /></span>
                <input id="q" placeholder="Search pages…" autocomplete="off">
                <div class="dm" id="sres"></div>
            </div>
            <div class="dw" id="usd">
                @php
                    $bits = preg_split('/\s+/', trim(auth()->user()->name)) ?: [];
                    $initials = strtoupper(collect($bits)->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join(''));
                    $hue = abs(crc32(auth()->user()->name)) % 360;
                @endphp
                <button class="ib" type="button" data-dd="usd" style="width:auto;gap:6px" aria-label="Profile">
                    <span class="av" style="--h:{{ $hue }};width:30px;height:30px">{{ $initials }}</span>
                </button>
                <div class="dm">
                    <div class="nt"><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->email }}</small></div>
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                </div>
            </div>
        </header>
        <main class="pg">
            @yield('content')
        </main>
    </div>

    <div class="toast-host" id="toastHost" aria-live="polite" aria-relevant="additions"></div>
    <script>window.__TOASTS__ = @json($flashToasts);</script>

    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Please confirm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmText"></div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn pr" id="confirmButton">Continue</button>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/select2/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}?v=13"></script>
    @stack('scripts')
</body>
</html>
