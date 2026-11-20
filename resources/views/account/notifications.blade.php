@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
    @php
        $total = $notifications->total();
        $showing = $filter === 'unread' ? 'Unread only' : 'All notices';
    @endphp
    <div class="nt-page">
        <div class="ph">
            <div>
                <x-back :href="route('more')" label="More" />
                <h1>{{ __('Notifications') }}</h1>
                <p>{{ $unread ? $unread.' unread · Gate Pass requests and decisions' : 'Gate Pass requests and decisions' }}</p>
            </div>
            <div class="ac">
                <a class="btn {{ $filter !== 'unread' ? 'pr' : '' }}" href="{{ route('notifications.index', [], false) }}">All</a>
                <a class="btn {{ $filter === 'unread' ? 'pr' : '' }}" href="{{ route('notifications.index', ['filter' => 'unread'], false) }}">Unread</a>
                @if($unread)
                    <form method="post" action="{{ route('notifications.read-all', [], false) }}">
                        @csrf
                        <button class="btn" type="submit">Mark all read</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="lv-stats g c3">
            <div class="card kp">
                <small>Unread</small>
                <b>{{ $unread }}</b>
                <span>Need attention</span>
            </div>
            <div class="card kp">
                <small>Showing</small>
                <b>{{ $total }}</b>
                <span>{{ $showing }}</span>
            </div>
            <div class="card kp">
                <small>Type</small>
                <b>Gate Pass</b>
                <span>Requests and decisions</span>
            </div>
        </div>

        <div data-live-list="notifications" data-page="{{ $notifications->currentPage() }}" data-last="{{ $notifications->lastPage() }}">
        <div class="nt-list" data-list-rows>
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $tone = $data['icon'] ?? 'wn';
                    $unreadItem = $notification->read_at === null;
                    $status = $data['status'] ?? ($unreadItem ? 'Unread' : 'Read');
                    $statusTone = match ($tone) {
                        'ok' => 'ok',
                        'er' => 'er',
                        default => 'wn',
                    };
                    $facts = array_filter([
                        'Pass' => $data['number'] ?? null,
                        'Employee' => $data['employee'] ?? null,
                        'Code' => $data['employee_code'] ?? null,
                        'Department' => $data['department'] ?? null,
                        'Parent' => $data['parent'] ?? null,
                        'Type' => $data['work_type'] ?? null,
                        'When' => $data['when'] ?? null,
                        'Status' => $status,
                    ], fn ($value) => filled($value));
                @endphp
                <form class="nt-item {{ $unreadItem ? 'is-new' : '' }}" method="post" action="{{ route('notifications.read', $notification->id, false) }}">
                    @csrf
                    <button class="nt-hit" type="submit" aria-label="Open {{ $data['title'] ?? 'notification' }}"></button>
                    <div class="nt-head">
                        <span class="nt-ico {{ $tone }}">
                            @if($tone === 'ok')
                                <x-i name="check" :size="18" />
                            @elseif($tone === 'er')
                                <x-i name="close" :size="18" />
                            @else
                                <x-i name="bell" :size="18" />
                            @endif
                        </span>
                        <div class="nt-copy">
                            <div class="nt-title">
                                <b>{{ $data['title'] ?? 'Notification' }}</b>
                                <span class="b {{ $unreadItem ? 'wn' : $statusTone }}">{{ $unreadItem ? 'Unread' : $status }}</span>
                            </div>
                            <small>{{ collect([$data['number'] ?? null, $data['work_type'] ?? null])->filter()->join(' · ') }}</small>
                            <span>{{ $data['when'] ?? $notification->created_at?->format('d M Y, h:i A') }}</span>
                        </div>
                    </div>
                    <div class="nt-body">
                        @if(!empty($data['body']))
                            <p>{{ $data['body'] }}</p>
                        @endif
                        @if($facts)
                            <dl class="nt-facts">
                                @foreach($facts as $label => $value)
                                    <div>
                                        <dt>{{ $label }}</dt>
                                        <dd>{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                        @if(!empty($data['reason']))
                            <div class="nt-reason">
                                <span>Reason</span>
                                <p>{{ $data['reason'] }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="nt-foot">
                        <span class="nt-time">{{ $notification->created_at?->diffForHumans() }}</span>
                        <span class="nt-open">Open Gate Pass <x-i name="right" :size="14" /></span>
                    </div>
                </form>
            @empty
                <div class="card cb empty-state" data-list-empty>
                    <h3>{{ __('You\'re all caught up') }}</h3>
                    <p>No notifications to show.</p>
                </div>
            @endforelse
        </div>

        @include('partials.list-footer', ['paginator' => $notifications])
        </div>
    </div>
@endsection
