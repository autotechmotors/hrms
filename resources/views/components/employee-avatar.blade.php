@props(['employee', 'size' => 32])
@php
    $name = $employee->full_name ?? 'Employee';
    $initials = strtoupper(collect(preg_split('/\s+/', trim($name)) ?: [])->map(fn ($part) => mb_substr($part, 0, 1))->filter()->take(2)->join('')) ?: '?';
    $hue = abs(crc32($name)) % 360;
    $hasPhoto = filled($employee->photograph_path ?? null);
@endphp
@if($hasPhoto)
    <img {{ $attributes->class('av av-img')->merge([
        'src' => route('employees.photo', $employee),
        'alt' => $name,
        'style' => '--h:'.$hue.';width:'.$size.'px;height:'.$size.'px',
        'loading' => 'lazy',
    ]) }}>
@else
    <span {{ $attributes->class('av')->merge([
        'style' => '--h:'.$hue.';width:'.$size.'px;height:'.$size.'px',
        'aria-hidden' => 'true',
    ]) }}>{{ $initials }}</span>
@endif
