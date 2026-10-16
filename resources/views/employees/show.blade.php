@extends('layouts.app')
@section('title', $employee->full_name)
@section('content')
    <div class="d-flex justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-0">{{ $employee->full_name }}</h1>
            <div class="text-secondary">{{ $employee->employee_code }} · {{ $employee->designation?->name }} · {{ $employee->department?->name }}</div>
        </div>
        @permission('employees.edit')<a class="btn btn-primary" href="{{ route('employees.edit', $employee) }}">Edit</a>@endpermission
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3"><div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Location</dt><dd class="col-sm-8">{{ $employee->location?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">Manager</dt><dd class="col-sm-8">{{ $employee->manager?->full_name ?? '—' }}</dd>
                    <dt class="col-sm-4">Category</dt><dd class="col-sm-8">{{ $employee->designation?->employeeType?->name ?? $employee->employeeType?->name }}</dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $employee->employmentStatus?->name }}</dd>
                    <dt class="col-sm-4">Joined</dt><dd class="col-sm-8">{{ $employee->joining_date?->format('d M Y') }}</dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $employee->email ?? '—' }}</dd>
                    <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $employee->phone ?? '—' }}</dd>
                    <dt class="col-sm-4">Probation</dt><dd class="col-sm-8">{{ $employee->probation_days !== null ? $employee->probation_days.' days' : '—' }}</dd>
                    <dt class="col-sm-4">Notice period</dt><dd class="col-sm-8">{{ $employee->notice_period_days !== null ? $employee->notice_period_days.' days' : '—' }}</dd>
                </dl>
            </div></div>
            <div class="card mb-3"><div class="card-header bg-white">Movement history</div>
                <ul class="list-group list-group-flush">
                    @forelse($employee->events as $event)
                        <li class="list-group-item"><strong>{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</strong> on {{ $event->effective_date->format('d M Y') }} — {{ $event->reason }}</li>
                    @empty
                        <li class="list-group-item text-secondary">No transfers or status changes recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-5">
            @permission('employee_documents.view')
            <div class="card mb-3"><div class="card-header bg-white">Documents</div><div class="card-body">
                <ul class="list-unstyled">
                    @foreach($employee->documents as $document)
                        <li class="mb-2 d-flex justify-content-between">
                            <a href="{{ route('employees.documents.download', [$employee, $document]) }}">{{ $document->title }}</a>
                            @permission('employee_documents.delete')
                                <form method="post" action="{{ route('employees.documents.destroy', [$employee, $document]) }}" data-confirm="Delete this document?">@csrf @method('delete')<button class="btn btn-link text-danger p-0">Delete</button></form>
                            @endpermission
                        </li>
                    @endforeach
                </ul>
                @permission('employee_documents.create')
                    <form method="post" action="{{ route('employees.documents.store', $employee) }}" enctype="multipart/form-data">
                        @csrf
                        <x-text-field name="title" label="Title" required />
                        <x-text-field name="document" label="File" type="file" required />
                        <button class="btn btn-outline-primary btn-sm">Upload</button>
                    </form>
                @endpermission
            </div></div>
            @endpermission

            @permission('bank_accounts.view')
            <div class="card mb-3"><div class="card-header bg-white">Bank accounts</div><div class="card-body">
                @forelse($employee->bankAccounts as $account)
                    <div class="mb-2">
                        <strong>{{ $account->bank_name }}</strong>
                        {{ $account->masked_account }}
                        @if($canViewAccount)<div class="small text-secondary">Full number: {{ $account->account_number }}</div>@endif
                        <div class="small">{{ $account->ifsc }} · {{ $account->is_active ? 'Active' : 'Historical' }} from {{ $account->effective_from->format('d M Y') }}</div>
                    </div>
                @empty
                    <p class="text-secondary">No bank account recorded.</p>
                @endforelse
                @permission('bank_accounts.manage')
                    <form method="post" action="{{ route('employees.bank-accounts.store', $employee) }}">
                        @csrf
                        <x-text-field name="beneficiary_name" label="Beneficiary" required />
                        <x-text-field name="bank_name" label="Bank" required />
                        <x-text-field name="account_number" label="Account number" required />
                        <x-text-field name="ifsc" label="IFSC" required />
                        <x-text-field name="effective_from" label="Effective from" type="date" required />
                        <button class="btn btn-outline-primary btn-sm">Save account</button>
                    </form>
                @endpermission
            </div></div>
            @endpermission

            @permission('employee_exits.create')
            <div class="card"><div class="card-header bg-white">Exit</div><div class="card-body">
                <p class="small text-secondary">This records resignation and final-working dates. Salary settlement is calculated in payroll, not on this screen.</p>
                <form method="post" action="{{ route('employees.exit.store', $employee) }}">
                    @csrf
                    <x-text-field name="resignation_date" label="Resignation date" type="date" :value="optional($employee->latestExit?->resignation_date)->toDateString()" required />
                    <x-text-field name="last_working_date" label="Last working date" type="date" :value="optional($employee->latestExit?->last_working_date)->toDateString()" required />
                    <x-text-field name="reason" label="Reason" :value="$employee->latestExit?->reason" required />
                    <x-select-field name="employment_status_id" label="Exit status" required>
                        <option value="">Select</option>
                        @foreach($exitStatuses as $status)
                            <option value="{{ $status->id }}" @selected(old('employment_status_id', $employee->latestExit?->employment_status_id) == $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </x-select-field>
                    <x-select-field name="fnf_status" label="Full and final status" required>
                        @foreach(['not_started' => 'Not started', 'pending' => 'Pending', 'completed' => 'Completed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('fnf_status', $employee->latestExit?->fnf_status ?? 'not_started') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select-field>
                    <x-text-field name="notes" label="Notes" type="textarea" :value="$employee->latestExit?->notes" />
                    <button class="btn btn-outline-primary btn-sm">Save exit</button>
                </form>
            </div></div>
            @endpermission
        </div>
    </div>
@endsection
