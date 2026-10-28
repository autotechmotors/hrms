@extends('layouts.app')
@section('title', $device->name)
@section('content')
    @php
        $statusClass = $device->status === 'online' ? 'ok' : ($device->status === 'offline' ? 'er' : '');
        $registerId = $device->register_id ?: config('dahua.register_id');
        $heard = (! empty($connection['register_id']) && strcasecmp((string) $connection['register_id'], (string) $registerId) === 0)
            ? $connection
            : null;
        $reply = data_get($lastResult, 'Response.Body.Detail.DebugInfo')
            ?: data_get($lastResult, 'Response.Body.ErrorMsg')
            ?: data_get($lastResult, 'Response.StatusCode');
    @endphp

    <div class="ph">
        <div>
            <a class="ed-back" href="{{ route('devices.index') }}"><x-i name="back" :size="14" />Dahua Devices</a>
            <h1>{{ $device->name }}</h1>
            <p>{{ $device->place ?: 'No place' }} · {{ $device->model ?: 'No model' }}</p>
        </div>
    </div>

    @include('operations.errors')

    <div class="ed">
    <section class="card ed-hero">
        <div class="ed-hero-main">
            <span class="dv-mark"><x-i name="cpu" :size="22" /></span>
            <div class="ed-hero-copy">
                <div class="ed-hero-top">
                    <span class="b {{ $statusClass }}">{{ ucfirst($device->status) }}</span>
                    <span class="b {{ $listenerOpen ? 'ok' : 'er' }}">Listener {{ $listenerOpen ? 'running' : 'stopped' }}</span>
                    @if (! empty($heard['online']))
                        <span class="b ok">Online</span>
                    @endif
                </div>
                <h2>{{ $device->name }}</h2>
                <p>Register ID {{ $registerId }}</p>
            </div>
        </div>
        <div class="ed-hero-meta">
            <div>
                <small>Place</small>
                <b>{{ $device->place ?: '—' }}</b>
            </div>
            <div>
                <small>IP address</small>
                <b>{{ $device->ip_address ?: '—' }}</b>
            </div>
            <div>
                <small>Serial</small>
                <b>{{ $device->serial ?: '—' }}</b>
            </div>
            <div>
                <small>Last check</small>
                <b>{{ \App\Support\Clock::dateTime($device->last_checked_at) }}</b>
            </div>
        </div>
    </section>

    <div class="ed-grid">
        <div class="ed-main">
            <section class="card ef-sec">
                <header>
                    <div>
                        <h3>People on this device</h3>
                        <p>{{ $device->terminalUsers->count() }} {{ \Illuminate\Support\Str::plural('person', $device->terminalUsers->count()) }} saved from the terminal or queued from HRMS</p>
                    </div>
                </header>
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>HRMS employee</th>
                                <th>On device</th>
                                @if ($canEdit)
                                    <th class="el-act">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($device->terminalUsers as $person)
                                <tr>
                                    <td><b>{{ $person->user_id }}</b></td>
                                    <td>{{ $person->name ?: '—' }}</td>
                                    <td>
                                        @if ($person->employee)
                                            {{ $person->employee->employee_code }} — {{ $person->employee->full_name }}
                                        @else
                                            Not in HRMS
                                        @endif
                                    </td>
                                    <td>
                                        <span class="b {{ $person->synced_at ? 'ok' : 'wn' }}">{{ $person->synced_at ? 'Yes' : 'Waiting' }}</span>
                                    </td>
                                    @if ($canEdit)
                                        <td class="el-act">
                                            <div class="el-ops">
                                                <button class="ib" type="button" title="View" data-bs-toggle="modal" data-bs-target="#personView"
                                                    data-user="{{ $person->user_id }}"
                                                    data-name="{{ $person->name }}"
                                                    data-employee="{{ $person->employee ? $person->employee->employee_code.' — '.$person->employee->full_name : 'Not in HRMS' }}"
                                                    data-state="{{ $person->synced_at ? 'On the device' : 'Waiting for the device' }}">
                                                    <x-i name="eye" :size="15" />
                                                </button>
                                                <button class="ib" type="button" title="Edit" data-bs-toggle="modal" data-bs-target="#personEdit"
                                                    data-action="{{ route('devices.terminal.update', $person) }}"
                                                    data-name="{{ $person->name }}">
                                                    <x-i name="pen" :size="15" />
                                                </button>
                                                <form method="post" action="{{ route('devices.terminal.destroy', $person) }}" data-confirm="Remove {{ $person->user_id }} from this device?">
                                                    @csrf
                                                    @method('delete')
                                                    <button class="ib er" type="submit" title="Delete"><x-i name="trash" :size="15" /></button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canEdit ? 5 : 4 }}" style="text-align:center;color:var(--mu);padding:32px">No people yet. The device list is read automatically every minute.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="ed-side">
            <section class="card ef-sec">
                <header>
                    <div>
                        <h3>Connection</h3>
                        <p>What this PC last heard from the device</p>
                    </div>
                </header>
                <div class="cb dv-conn">
                    <div class="ed-kv ed-kv-1">
                        <div>
                            <span>Listener</span>
                            <b>{{ $listenerOpen ? 'Running' : 'Stopped' }} on port {{ $pushPort }}</b>
                        </div>
                        <div>
                            <span>Last contact</span>
                            <b>
                                @if ($heard)
                                    {{ \App\Support\Clock::stamp($heard['last_seen_at']) }}
                                    @if (! empty($heard['ip']))
                                        · {{ $heard['ip'] }}
                                    @endif
                                @else
                                    No contact yet
                                @endif
                            </b>
                        </div>
                        <div>
                            <span>Last reply</span>
                            <b>{{ $reply ?: '—' }}</b>
                        </div>
                    </div>
                    <p>Server {{ $pushHost }}, port {{ $pushPort }}.
                        @if (config('dahua.auto_listen'))
                            Listener auto-starts with <code>schedule:work</code> / cron (<code>DAHUA_AUTO_LISTEN=true</code>).
                        @else
                            On live, the website :443 handles EZAPI — no local listener.
                        @endif
                        Terminal AutoRegister ID must match this device register ID.</p>
                </div>
            </section>

            @if ($canEdit)
                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Enroll employee</h3>
                            <p>The device user id is the employee code</p>
                        </div>
                    </header>
                    <div class="cb">
                        <form class="dv-enroll" method="post" action="{{ route('devices.enroll', $device) }}">
                            @csrf
                            <label>Employee
                                <select class="in" name="employee_id" required>
                                    <option value="">Select</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->employee_code }} — {{ $employee->full_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Expires
                                <input class="in" type="date" name="expires_on">
                            </label>
                            <button class="btn pr" type="submit">Enroll</button>
                        </form>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Actions</h3>
                            <p>Change this device or remove it</p>
                        </div>
                    </header>
                    <div class="cb dv-act">
                        <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#editDeviceModal">
                            <x-i name="pen" :size="14" />Edit device
                        </button>
                        <form method="post" action="{{ route('devices.destroy', $device) }}" data-confirm="Remove {{ $device->name }}? People enrolled on it are removed from this list.">
                            @csrf
                            @method('delete')
                            <button class="btn dv-danger" type="submit"><x-i name="trash" :size="14" />Delete device</button>
                        </form>
                    </div>
                </section>
            @endif
        </aside>
    </div>
    </div>

    @if ($canEdit)
        <div class="modal fade" id="editDeviceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="post" action="{{ route('devices.update', $device) }}">
                    @csrf
                    @method('put')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit device</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('operations.devices._fields', ['device' => $device, 'bindOld' => $errors->any()])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn pr" type="submit">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal fade" id="personView" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Person on device</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="ed-kv ed-kv-1">
                            <div><span>User ID</span><b id="personViewUser">—</b></div>
                            <div><span>Name</span><b id="personViewName">—</b></div>
                            <div><span>HRMS employee</span><b id="personViewEmployee">—</b></div>
                            <div><span>State</span><b id="personViewState">—</b></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="personEdit" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="post" id="personEditForm" action="">
                    @csrf
                    @method('put')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit name</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label>Name on the device
                            <input class="in" name="name" id="personEditName" required maxlength="120">
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn pr" type="submit">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.getElementById('personView')?.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) return;
            document.getElementById('personViewUser').textContent = button.dataset.user || '—';
            document.getElementById('personViewName').textContent = button.dataset.name || '—';
            document.getElementById('personViewEmployee').textContent = button.dataset.employee || '—';
            document.getElementById('personViewState').textContent = button.dataset.state || '—';
        });
        document.getElementById('personEdit')?.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) return;
            document.getElementById('personEditForm').action = button.dataset.action || '';
            document.getElementById('personEditName').value = button.dataset.name || '';
        });
    </script>
@endpush
