@php
    $current = $device ?? null;
    $field = function (string $name, mixed $fallback = '') use ($current, $bindOld) {
        $stored = $current?->{$name} ?? $fallback;

        return $bindOld ? old($name, $stored) : $stored;
    };
@endphp
<div class="dv-fields">
    <label class="dv-span">Name
        <input class="in" name="name" value="{{ $field('name') }}" required maxlength="120">
    </label>
    <label>Model
        <input class="in" name="model" value="{{ $field('model') }}" maxlength="120">
    </label>
    <label>Serial
        <input class="in" name="serial" value="{{ $field('serial') }}" maxlength="120">
    </label>
    <label>Register ID
        <input class="in" name="register_id" value="{{ $field('register_id', config('dahua.register_id')) }}" maxlength="64" {{ $current ? 'required' : '' }}>
    </label>
    <label>IP address
        <input class="in" name="ip_address" value="{{ $field('ip_address') }}" placeholder="192.168.1.10" maxlength="45">
    </label>
    <label class="dv-span">Place
        <input class="in" name="place" value="{{ $field('place') }}" maxlength="120">
    </label>
    @if ($current)
        <label class="dv-span">Status
            <select class="in" name="status" data-native required>
                @foreach (['unknown', 'online', 'offline'] as $status)
                    <option value="{{ $status }}" @selected($field('status', 'unknown') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </label>
    @endif
</div>
