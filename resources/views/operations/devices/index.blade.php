@extends('layouts.app')
@section('title', 'Dahua Devices')
@section('content')
    <div class="ph">
        <div>
            <h1>Dahua Devices</h1>
            <p>Register a device here, then open it to enroll employees.</p>
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

    <div class="g c3">
        <div class="card kp">
            <small>Devices</small>
            <b>{{ $devices->count() }}</b>
            <span>Registered on this company</span>
        </div>
        <div class="card kp">
            <small>Enrolled people</small>
            <b>{{ $enrolledCount }}</b>
            <span>Across every device</span>
        </div>
        <div class="card kp">
            <small>Listener</small>
            <b>{{ $listenerOpen ? 'Running' : 'Stopped' }}</b>
            <span>Port {{ config('dahua.port') }} on this PC</span>
        </div>
    </div>

    <section class="card ef-sec">
        <header>
            <div>
                <h3>All devices</h3>
                <p>Open a device to see who is enrolled. Edit and delete stay in Actions.</p>
            </div>
        </header>
        <div class="tw">
            <table>
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Place</th>
                        <th>Register ID</th>
                        <th>Status</th>
                        <th>Enrolled</th>
                        <th>Last check</th>
                        <th class="el-act">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $device)
                        @php
                            $statusClass = $device->status === 'online' ? 'ok' : ($device->status === 'offline' ? 'er' : '');
                        @endphp
                        <tr>
                            <td>
                                <b>{{ $device->name }}</b>
                                <small class="dv-sub">{{ trim($device->model.' '.$device->serial) ?: 'No model' }}</small>
                            </td>
                            <td>{{ $device->place ?: '—' }}</td>
                            <td>{{ $device->register_id ?: config('dahua.register_id') }}</td>
                            <td><span class="b {{ $statusClass }}">{{ ucfirst($device->status) }}</span></td>
                            <td>{{ $device->users_count }}</td>
                            <td>{{ \App\Support\Clock::dateTime($device->last_checked_at) }}</td>
                            <td class="el-act">
                                <div class="el-ops">
                                    <a class="ib" href="{{ route('devices.show', $device) }}" title="View"><x-i name="eye" :size="15" /></a>
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
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;color:var(--mu);padding:32px">No devices yet. Add one to start enrolling people.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

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
