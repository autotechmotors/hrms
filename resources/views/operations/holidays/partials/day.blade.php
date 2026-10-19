@php
    $isToday = $date === $today;
    $classes = ['hc-day'];
    if ($isToday) {
        $classes[] = 'is-today';
    }
    if ($isOutside) {
        $classes[] = 'is-outside';
    }
    if ($holiday) {
        $classes[] = 'has-holiday';
        $classes[] = 'kind-'.$holiday->kind;
    }
    if ($canEdit) {
        $classes[] = 'is-clickable';
    }
    $dayNum = (int) substr($date, -2);
@endphp
@if($holiday)
    <button
        type="button"
        class="{{ implode(' ', $classes) }}"
        title="{{ $holiday->name }} ({{ $holiday->kind }})"
        data-bs-toggle="modal"
        data-bs-target="#holidayViewModal"
        data-name="{{ $holiday->name }}"
        data-kind="{{ $holiday->kind }}"
        data-date="{{ $holiday->holiday_date->format('D, d M Y') }}"
        data-iso="{{ $date }}"
        @if($canEdit) data-delete="{{ route('holidays.destroy', $holiday) }}" @endif
    >
        <span class="hc-num">{{ $dayNum }}</span>
        @unless($isOutside)
            <span class="hc-mark">{{ \Illuminate\Support\Str::limit($holiday->name, 10) }}</span>
        @endunless
    </button>
@elseif($canEdit)
    <button
        type="button"
        class="{{ implode(' ', $classes) }}"
        title="Add holiday on {{ $date }}"
        data-bs-toggle="modal"
        data-bs-target="#holidayModal"
        data-date="{{ $date }}"
        data-iso="{{ $date }}"
    >
        <span class="hc-num">{{ $dayNum }}</span>
    </button>
@else
    <span class="{{ implode(' ', $classes) }}" data-iso="{{ $date }}"><span class="hc-num">{{ $dayNum }}</span></span>
@endif
