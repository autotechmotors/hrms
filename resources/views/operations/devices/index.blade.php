@extends('layouts.app')
@section('title', 'Dahua Devices')
@section('content')
    @php
        $pc = $localPc ?? ['available' => false];
        $showPc = $canEdit && ($pc['available'] ?? false);
        $listenerLabel = $listenerOpen ? 'Running' : 'Stopped';
        $listenerMode = config('dahua.auto_listen')
            ? 'PC auto-start'
            : ((int) config('dahua.port') === 443 ? 'Live web :443' : 'Push service');
        $autoOn = (bool) ($pc['autostart'] ?? false);
    @endphp

    <div class="ph">
        <div>
            <h1>Dahua Devices</h1>
            <p>Register terminals, enroll people, and keep the local push server healthy.</p>
        </div>
        @if ($canEdit)
            <div class="ac">
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                    <x-i name="plus" :size="14" />Add device
                </button>
            </div>
        @endif
    </div>

    @include('operations.errors')

    <div class="dv-page">
        <section class="card dv-overview">
            <div class="dv-overview-top">
                <div class="dv-overview-title">
                    <span class="dv-mark"><x-i name="cpu" :size="22" /></span>
                    <div>
                        <div class="dv-overview-badges">
                            <span class="b {{ $listenerOpen ? 'ok' : 'er' }}">Listener {{ $listenerLabel }}</span>
                            <span class="b ac">{{ $listenerMode }}</span>
                        </div>
                        <h2>Attendance device hub</h2>
                        <p>Port {{ config('dahua.port') }} · Register ID {{ config('dahua.register_id') }}</p>
                    </div>
                </div>
            </div>
            <div class="dv-kpis">
                <div class="dv-kpi">
                    <small>Devices</small>
                    <b>{{ $devices->count() }}</b>
                </div>
                <div class="dv-kpi">
                    <small>Enrolled people</small>
                    <b>{{ $enrolledCount }}</b>
                </div>
                <div class="dv-kpi">
                    <small>Listener</small>
                    <b class="{{ $listenerOpen ? 'up' : 'dn' }}">{{ $listenerLabel }}</b>
                </div>
                <div class="dv-kpi">
                    <small>Push host</small>
                    <b class="dv-kpi-host">{{ config('dahua.host') }}:{{ config('dahua.port') }}</b>
                </div>
            </div>
        </section>

        @if ($showPc)
            <section class="card dv-pc">
                <div class="dv-pc-head">
                    <div>
                        <div class="dv-pc-title">
                            <h3>Local PC server</h3>
                            <span class="b {{ $autoOn ? 'ok' : 'er' }}">{{ $autoOn ? 'Auto-start ON' : 'Auto-start OFF' }}</span>
                        </div>
                        <p>Background MySQL check, HRMS web, and Dahua listener — no daily CMD.</p>
                    </div>
                    <div class="dv-pc-actions">
                        @if (! $autoOn)
                            <form method="post" action="{{ route('devices.local-pc.enable') }}">
                                @csrf
                                <button class="btn pr" type="submit"><x-i name="set" :size="14" />Enable auto-start</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('devices.local-pc.disable') }}" data-confirm="Turn off PC login auto-start?">
                                @csrf
                                <button class="btn" type="submit"><x-i name="minus" :size="14" />Disable auto-start</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('devices.local-pc.start') }}">
                            @csrf
                            <button class="btn" type="submit"><x-i name="play" :size="14" />Start now</button>
                        </form>
                        <form method="post" action="{{ route('devices.local-pc.stop') }}" data-confirm="Stop Dahua listener and scheduler now?">
                            @csrf
                            <button class="btn dv-danger" type="submit"><x-i name="stop" :size="14" />Stop now</button>
                        </form>
                    </div>
                </div>

                <div class="dv-health">
                    <div class="dv-health-item {{ ($pc['mysql'] ?? false) ? 'is-up' : 'is-down' }}">
                        <small>MySQL</small>
                        <div class="dv-health-row">
                            <b>:3306</b>
                            <span>{{ ($pc['mysql'] ?? false) ? 'Up' : 'Down' }}</span>
                        </div>
                    </div>
                    <div class="dv-health-item {{ ($pc['web'] ?? false) ? 'is-up' : 'is-down' }}">
                        <small>Web UI</small>
                        <div class="dv-health-row">
                            <b>:{{ $pc['web_port'] ?? 8000 }}</b>
                            <span>{{ ($pc['web'] ?? false) ? 'Up' : 'Down' }}</span>
                        </div>
                    </div>
                    <div class="dv-health-item {{ ($pc['listener'] ?? false) ? 'is-up' : 'is-down' }}">
                        <small>Dahua</small>
                        <div class="dv-health-row">
                            <b>:{{ $pc['push_port'] ?? config('dahua.port') }}</b>
                            <span>{{ ($pc['listener'] ?? false) ? 'Up' : 'Down' }}</span>
                        </div>
                    </div>
                    <div class="dv-health-item dv-endpoints">
                        <small>Endpoints</small>
                        <div class="dv-endpoint-lines">
                            <span><em>UI</em> http://{{ $pc['host'] }}:{{ $pc['web_port'] }}</span>
                            <span><em>Push</em> {{ $pc['host'] }}:{{ $pc['push_port'] }}</span>
                        </div>
                    </div>
                </div>

                <p class="dv-pc-hint">One-time MySQL Automatic service: run <code>scripts\install-xampp-autostart.bat</code> as Administrator.</p>
            </section>
        @endif

        <section class="card ef-sec dv-list-card">
            <header>
                <div>
                    <h3>All devices</h3>
                    <p>{{ $devices->count() }} {{ \Illuminate\Support\Str::plural('terminal', $devices->count()) }} · open one to enroll employees</p>
                </div>
            </header>

            @if ($devices->isEmpty())
                <div class="dv-empty">
                    <span class="dv-mark lg"><x-i name="cpu" :size="26" /></span>
                    <h4>No devices yet</h4>
                    <p>Add your first Dahua terminal, then enroll employees from the device page.</p>
                    @if ($canEdit)
                        <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                            <x-i name="plus" :size="14" />Add device
                        </button>
                    @endif
                </div>
            @else
                <div class="dv-grid">
                    @foreach ($devices as $device)
                        @php
                            $statusClass = $device->status === 'online' ? 'ok' : ($device->status === 'offline' ? 'er' : 'wn');
                        @endphp
                        <article class="dv-tile">
                            <a class="dv-tile-main" href="{{ route('devices.show', $device) }}">
                                <span class="dv-mark sm"><x-i name="cpu" :size="18" /></span>
                                <div class="dv-tile-copy">
                                    <div class="dv-tile-top">
                                        <h4>{{ $device->name }}</h4>
                                        <span class="b {{ $statusClass }}">{{ ucfirst($device->status) }}</span>
                                    </div>
                                    <p>{{ $device->place ?: 'No place' }} · {{ trim($device->model.' '.$device->serial) ?: 'No model' }}</p>
                                    <div class="dv-tile-meta">
                                        <span>{{ $device->users_count }} enrolled</span>
                                        <span>ID {{ $device->register_id ?: config('dahua.register_id') }}</span>
                                        <span>{{ \App\Support\Clock::dateTime($device->last_checked_at) }}</span>
                                    </div>
                                </div>
                            </a>
                            <div class="dv-tile-ops">
                                <a class="ib" href="{{ route('devices.show', $device) }}" title="Open"><x-i name="eye" :size="15" /></a>
                                @if ($canEdit)
                                    <button class="ib" type="button" title="Edit" data-bs-toggle="modal" data-bs-target="#editDevice{{ $device->id }}">
                                        <x-i name="pen" :size="15" />
                                    </button>
                                    <form method="post" action="{{ route('devices.destroy', $device) }}" data-confirm="Remove {{ $device->name }}? People enrolled on it are removed from this list.">
                                        @csrf
                                        @method('delete')
                                        <button class="ib er" type="submit" title="Delete"><x-i name="trash" :size="15" /></button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    @if ($canEdit)
        <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="post" action="{{ route('devices.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add device</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('operations.devices._fields', ['device' => null, 'bindOld' => $errors->any() && ! old('_method')])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn pr" type="submit">Add device</button>
                    </div>
                </form>
            </div>
        </div>

        @foreach ($devices as $device)
            <div class="modal fade" id="editDevice{{ $device->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form class="modal-content" method="post" action="{{ route('devices.update', $device) }}">
                        @csrf
                        @method('put')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit {{ $device->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @include('operations.devices._fields', ['device' => $device, 'bindOld' => false])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn pr" type="submit">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
@endsection
