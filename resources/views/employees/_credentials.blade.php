@php
    $counts = $counts ?? [];
    $kinds = [
        'password' => ['icon' => 'lock', 'label' => 'Password'],
        'card' => ['icon' => 'card', 'label' => 'Card'],
        'finger' => ['icon' => 'finger', 'label' => 'Finger'],
        'face' => ['icon' => 'face', 'label' => 'Face'],
    ];
@endphp
<div class="ac-cred" data-state="{{ route('employees.credentials.state', $employee) }}">
    @foreach($kinds as $kind => $meta)
        @php $total = (int) ($counts[$kind] ?? 0); @endphp
        @if($canAdd)
            <button
                type="button"
                class="ac-item {{ $total > 0 ? 'on' : '' }}"
                data-kind="{{ $kind }}"
                data-url="{{ route('employees.credentials.collect', $employee) }}"
                title="{{ $total > 0 ? $meta['label'].' stored. Click to add another on the terminal.' : 'Click to add '.$meta['label'].' on the terminal.' }}"
            >
                <x-i :name="$meta['icon']" :size="15" />
                <b>{{ $total }}</b>
            </button>
        @else
            <span class="ac-item {{ $total > 0 ? 'on' : '' }}" title="{{ $meta['label'] }} {{ $total }}">
                <x-i :name="$meta['icon']" :size="15" />
                <b>{{ $total }}</b>
            </span>
        @endif
    @endforeach
</div>
