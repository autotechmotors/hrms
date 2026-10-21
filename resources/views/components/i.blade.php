@props(['name', 'size' => 16])
@php
    $icons = [
        'dash' => 'M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'clock' => 'M12 6v6l4 2M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20',
        'cal' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2',
        'sun' => 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2',
        'wallet' => 'M20 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM16 14h.01M4 7V5a2 2 0 0 1 2-2h12',
        'layers' => 'M12 2 2 7l10 5 10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
        'minus' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M8 12h8',
        'cpu' => 'M6 4h12v16H6zM9 9h6v6H9zM9 1v3M15 1v3M9 20v3M15 20v3',
        'chart' => 'M3 3v18h18M7 14l4-4 4 4 5-6',
        'bank' => 'M3 21h18M5 21V10M19 21V10M9 21V10M15 21V10M2 10l10-7 10 7',
        'shield' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10',
        'building' => 'M3 21h18M6 21V3h12v18M10 7h4M10 11h4M10 15h4',
        'set' => 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
        'log' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h8',
        'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
        'search' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16M21 21l-4.3-4.3',
        'down' => 'M6 9l6 6 6-6',
        'help' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M9 9a3 3 0 0 1 5.1 2c0 2-3 3-3 3M12 17h.01',
        'menu' => 'M3 6h18M3 12h18M3 18h18',
        'plus' => 'M12 5v14M5 12h14',
        'dl' => 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'grid' => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
        'reset' => 'M3 12a9 9 0 1 0 3-6.7M3 4v5h5',
        'pen' => 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        'trash' => 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6',
        'eye' => 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6',
        'back' => 'M19 12H5M12 19l-7-7 7-7',
        'mail' => 'M4 4h16v16H4zM4 4l8 8 8-8',
        'phone' => 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.8.3 1.6.6 2.3a2 2 0 0 1-.5 2.1L8.1 9.1a16 16 0 0 0 6 6l1.9-1.1a2 2 0 0 1 2.1-.4c.7.3 1.5.5 2.3.6a2 2 0 0 1 1.6 2.1z',
        'file' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6',
        'lock' => 'M7 11V7a5 5 0 0 1 10 0v4M5 11h14v10H5z',
        'card' => 'M3 7h18v12H3zM3 11h18',
        'finger' => 'M12 11a2 2 0 0 1 2 2c0 2-2 2-2 4M8 12a4 4 0 0 1 8 0c0 3-4 3-4 6M5 13a7 7 0 0 1 14 0',
        'face' => 'M8 21v-2a4 4 0 0 1 8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
    ];
    $raw = $icons[$name] ?? '';
    $parts = array_values(array_filter(explode('M', $raw), fn ($part) => $part !== ''));
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @foreach($parts as $part)
        <path d="M{{ $part }}"/>
    @endforeach
</svg>
