@props([
    'name',
    'label',
    'hint' => null,
    'checked' => false,
    'on' => 'On',
    'off' => 'Off',
    'disabled' => false,
])
@php
    $isOn = filter_var(old($name, $checked), FILTER_VALIDATE_BOOLEAN);
    $switchId = $name.'-switch';
@endphp
<div class="rl-rule">
    <span class="rl-k">{{ __($label) }}</span>
    <input type="hidden" name="{{ $name }}" value="{{ $disabled && $isOn ? '1' : '0' }}">
    <label class="rl-switch {{ $disabled ? 'is-locked' : '' }}" for="{{ $switchId }}">
        <input type="checkbox" id="{{ $switchId }}" name="{{ $name }}" value="1" @checked($isOn) @disabled($disabled) data-rl-switch data-on="{{ __($on) }}" data-off="{{ __($off) }}">
        <span class="rl-ui"></span>
        <span class="rl-txt">{{ $isOn ? __($on) : __($off) }}</span>
    </label>
    @if($hint)
        <p>{{ __($hint) }}</p>
    @endif
</div>
