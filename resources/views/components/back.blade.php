@props(['href', 'label'])
<a {{ $attributes->merge(['class' => 'ed-back']) }} href="{{ $href }}" data-back aria-label="{{ $label }}">
    <x-i name="back" :size="16" />
</a>
