@extends('layouts.app')
@section('title', 'Dahua Devices')
@section('content')
    <div class="ph">
        <div>
            <h1>Dahua Devices</h1>
            <p>Register terminals and who is enrolled. Checking a device only records the time. Punches are not pulled in.</p>
        </div>
    </div>
    @include('operations.errors')
    @if($canEdit)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <form method="post" action="{{ route('devices.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                @csrf
                <label>Name<br><input class="in" name="name" required></label>
                <label>Model<br><input class="in" name="model"></label>
                <label>Serial<br><input class="in" name="serial"></label>
                <label>IP<br><input class="in" name="ip_address" placeholder="192.168.1.10"></label>
                <label>Place<br><input class="in" name="place"></label>
                <button class="btn pr" type="submit">Add device</button>
            </form>
        </section>
    @endif
    <section class="card">
        <div class="tw">
            <table>
                <thead><tr><th>Name</th><th>Place</th><th>Status</th><th>Last check</th><th>Enrolled</th></tr></thead>
                <tbody>
                @forelse($devices as $device)
                    <tr class="cl" onclick="location.href='{{ route('devices.show', $device) }}'">
                        <td><b>{{ $device->name }}</b><br><small style="color:var(--mu)">{{ $device->model }} {{ $device->serial }}</small></td>
                        <td>{{ $device->place ?: '—' }}</td>
                        <td><span class="b {{ $device->status === 'online' ? 'ok' : ($device->status === 'offline' ? 'er' : '') }}">{{ $device->status }}</span></td>
                        <td>{{ $device->last_checked_at?->timezone('Asia/Kolkata')->format('d M Y H:i') ?: '—' }}</td>
                        <td>{{ $device->users_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No devices registered</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
