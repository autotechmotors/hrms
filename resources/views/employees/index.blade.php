@extends('layouts.app')
@section('title', 'Employees')
@section('content')
    <div class="ph">
        <div>
            <h1>Employees</h1>
            <p>{{ $employees->total() }} people in your scope</p>
        </div>
        <div class="ac">
            @permission('employees.import')
                <a class="btn" href="{{ route('employees.import') }}"><x-i name="dl" :size="14" />Import</a>
            @endpermission
            @permission('employees.export')
                <a class="btn" href="{{ route('employees.export') }}"><x-i name="dl" :size="14" />Export</a>
            @endpermission
            @permission('employees.create')
                <a class="btn pr" href="{{ route('employees.create') }}"><x-i name="plus" :size="14" />Add employee</a>
            @endpermission
        </div>
    </div>
    <section class="card">
        <form class="tbar" method="get">
            <div class="sr" style="width:260px">
                <span><x-i name="search" /></span>
                <input name="q" value="{{ request('q') }}" placeholder="Search employees">
            </div>
            <select class="in" name="department_id" data-submit>
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            <select class="in" name="employment_status_id" data-submit>
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected(request('employment_status_id') == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        </form>
        <div class="tw">
            <table>
                <thead><tr><th>Employee</th><th>ID</th><th>Department</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($employees as $employee)
                    @php
                        $status = $employee->employmentStatus?->name ?? '—';
                        $badge = $employee->employmentStatus?->is_exit ? 'er' : (str_contains(strtolower($status), 'probation') ? 'wn' : 'ok');
                        $initials = strtoupper(collect(preg_split('/\s+/', $employee->full_name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join(''));
                    @endphp
                    <tr class="cl" onclick="location.href='{{ route('employees.show', $employee) }}'">
                        <td>
                            <div class="who">
                                <span class="av" style="--h:{{ abs(crc32($employee->full_name)) % 360 }};width:28px;height:28px">{{ $initials }}</span>
                                <div><b>{{ $employee->full_name }}</b><small>{{ $employee->designation?->name }}</small></div>
                            </div>
                        </td>
                        <td>{{ $employee->employee_code }}</td>
                        <td>{{ $employee->department?->name }}</td>
                        <td><span class="b {{ $badge }}">{{ $status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--mu);padding:32px">No employees match your filters</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pgn">
            <span>Showing {{ $employees->count() }} of {{ $employees->total() }} employees</span>
            <div>{{ $employees->links() }}</div>
        </div>
    </section>
@endsection
