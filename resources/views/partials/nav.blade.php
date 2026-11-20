@php $navGroups = \App\Support\Navigation::visible(auth()->user()); @endphp
<div class="logo"><img class="brand-icon" src="{{ asset('icons/icon-192.png') }}" alt="" width="28" height="28"><span class="lb">{{ config('app.name') }}</span></div>
<nav class="nv" id="nv">
    @foreach($navGroups as $label => $links)
        <button class="gh" type="button"><span>{{ __($label) }}</span><x-i name="down" :size="12" /></button>
        <div class="ng">
            @foreach($links as $link)
                <a class="ni {{ request()->routeIs(...$link['active']) ? 'on' : '' }}" href="{{ route($link['route']) }}" data-page="{{ $link['page'] }}"><x-i :name="$link['icon']" :size="17" /><span class="lb">{{ __($link['label']) }}</span></a>
            @endforeach
        </div>
    @endforeach
</nav>
