<nav class="lang-set" aria-label="{{ __('Language') }}">
    @foreach (['en' => 'EN', 'hi' => 'हि', 'gu' => 'ગુ'] as $code => $short)
        <a href="{{ route('locale.update', $code) }}" hreflang="{{ $code }}" lang="{{ $code }}" class="{{ app()->getLocale() === $code ? 'on' : '' }}">{{ $short }}</a>
    @endforeach
</nav>
