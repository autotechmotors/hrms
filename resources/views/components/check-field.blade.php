@props(['name', 'label', 'checked' => false])
@php
    $current = old($name, $checked);
    $isChecked = filter_var($current, FILTER_VALIDATE_BOOLEAN);
@endphp
<div class="form-check mb-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input class="form-check-input" type="checkbox" name="{{ $name }}" id="{{ $name }}" value="1" @checked($isChecked)>
    <label class="form-check-label" for="{{ $name }}">{{ $label }}</label>
</div>
