@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false])
<div class="mb-3">
    <label class="form-label" for="{{ $name }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
    @if($type === 'textarea')
        <textarea name="{{ $name }}" id="{{ $name }}" class="form-control @error($name) is-invalid @enderror" @required($required) {{ $attributes }}>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $value) }}" class="form-control @error($name) is-invalid @enderror" @required($required) {{ $attributes }}>
    @endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
