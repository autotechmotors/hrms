@props(['name', 'label', 'required' => false])
<div class="mb-3">
    <label class="form-label" for="{{ $name }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
    <select name="{{ $name }}" id="{{ $name }}" class="form-select @error($name) is-invalid @enderror" @required($required) {{ $attributes }}>
        {{ $slot }}
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
