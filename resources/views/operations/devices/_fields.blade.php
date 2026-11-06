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
        <input class="in" name="register_id" value="{{ $field('register_id', config('devices.register_id')) }}" maxlength="64" {{ $current ? 'required' : '' }} placeholder="HIK… for Hikvision">
    </label>
    <label>IP address
        <input class="in" name="ip_address" value="{{ $field('ip_address') }}" placeholder="192.168.1.10" maxlength="45">
    </label>
    <label>Port
        <input class="in" name="port" value="{{ $field('port') }}" placeholder="80" inputmode="numeric" maxlength="5">
    </label>
    <label>Username
        <input class="in" name="username" value="{{ $field('username') }}" maxlength="64" autocomplete="off">
    </label>
    <label>Password
        <input class="in" type="password" name="password" value="" maxlength="64" autocomplete="new-password" placeholder="{{ $current && $current->password ? 'Saved. Leave blank to keep it.' : 'Terminal password' }}">
    </label>
    <label>Place
        <input class="in" name="place" value="{{ $field('place') }}" maxlength="120">
    </label>
    <label>Machine role
        <select class="in" name="gate_kind" data-native required>
            @foreach (['attendance' => 'Attendance IN / OUT', 'gate' => 'Gate OUT (pass only)'] as $kind => $label)
                <option value="{{ $kind }}" @selected($field('gate_kind', 'attendance') === $kind)>{{ $label }}</option>
            @endforeach
        </select>
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
