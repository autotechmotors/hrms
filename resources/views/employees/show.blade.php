@extends('layouts.app')
@section('title', $employee->full_name)
@section('content')
    @php
        $status = $employee->employmentStatus?->name ?? '—';
        $badge = $employee->employmentStatus?->is_exit ? 'er' : (str_contains(strtolower($status), 'probation') ? 'wn' : 'ok');
        $category = $employee->designation?->employeeType?->name ?? $employee->employeeType?->name;
        $isSuper = auth()->user()->isSuperAdmin();
    @endphp

    <div class="ph">
        <div>
            <a class="ed-back" href="{{ route('employees.index') }}"><x-i name="back" :size="14" />Employees</a>
            <h1>{{ $employee->full_name }}</h1>
            <p>{{ $employee->employee_code }} · {{ $employee->designation?->name ?? '—' }} · {{ $employee->department?->name ?? '—' }}</p>
        </div>
        <div class="ac">
            @if($isSuper)
                <a class="btn" href="{{ route('employees.edit', $employee) }}"><x-i name="pen" :size="14" />Edit</a>
            @endif
        </div>
    </div>

    <div class="ed">
        <section class="card ed-hero">
            <div class="ed-hero-main">
                <x-employee-avatar :employee="$employee" :size="72" class="ed-av" />
                <div class="ed-hero-copy">
                    <div class="ed-hero-top">
                        <span class="el-id">{{ $employee->employee_code }}</span>
                        <span class="b {{ $badge }}">{{ $status }}</span>
                        @if($employee->is_blocked)
                            <span class="b er">Blocked</span>
                        @endif
                    </div>
                    <h2>{{ $employee->full_name }}</h2>
                    <p>{{ $employee->designation?->name ?? 'No designation' }}@if($category) · {{ $category }}@endif</p>
                    <div class="ed-chips">
                        <span>{{ $employee->department?->name ?? 'No department' }}</span>
                        <span>{{ $employee->branch?->name ?? 'No branch' }}</span>
                        <span>{{ $employee->location?->name ?? 'No location' }}</span>
                    </div>
                </div>
            </div>
            <div class="ed-hero-meta">
                <div>
                    <small>Email</small>
                    <b>{{ $employee->email ?? '—' }}</b>
                </div>
                <div>
                    <small>Phone</small>
                    <b>{{ $employee->phone ?? '—' }}</b>
                </div>
                <div>
                    <small>Joined</small>
                    <b>{{ $employee->joining_date?->format('d M Y') ?? '—' }}</b>
                </div>
                <div>
                    <small>Manager</small>
                    <b>{{ $employee->manager?->full_name ?? '—' }}</b>
                </div>
            </div>
        </section>

        <div class="ed-grid">
            <div class="ed-main">
                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Profile</h3>
                            <p>Personal and contact details</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="ed-kv">
                            <div><span>First name</span><b>{{ $employee->first_name }}</b></div>
                            <div><span>Middle name</span><b>{{ $employee->middle_name ?: '—' }}</b></div>
                            <div><span>Last name</span><b>{{ $employee->last_name }}</b></div>
                            <div><span>Gender</span><b>{{ $employee->gender ? ucfirst($employee->gender) : '—' }}</b></div>
                            <div><span>Date of birth</span><b>{{ $employee->date_of_birth?->format('d M Y') ?? '—' }}</b></div>
                            <div><span>Email</span><b>{{ $employee->email ?? '—' }}</b></div>
                            <div><span>Phone</span><b>{{ $employee->phone ?? '—' }}</b></div>
                            <div><span>Alternate phone</span><b>{{ $employee->alternate_phone ?? '—' }}</b></div>
                        </div>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Placement</h3>
                            <p>Where they sit in the organisation</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="ed-kv">
                            <div><span>Company</span><b>{{ $employee->company?->name ?? '—' }}</b></div>
                            <div><span>Branch</span><b>{{ $employee->branch?->name ?? '—' }}</b></div>
                            <div><span>Location</span><b>{{ $employee->location?->name ?? '—' }}</b></div>
                            <div><span>Department</span><b>{{ $employee->department?->name ?? '—' }}</b></div>
                            <div><span>Designation</span><b>{{ $employee->designation?->name ?? '—' }}</b></div>
                            <div><span>Category</span><b>{{ $category ?: '—' }}</b></div>
                            <div><span>Status</span><b>{{ $status }}</b></div>
                            <div><span>Reporting manager</span><b>{{ $employee->manager?->full_name ?? '—' }}@if($employee->manager) <small class="ed-muted">({{ $employee->manager->employee_code }})</small>@endif</b></div>
                        </div>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Employment terms</h3>
                            <p>Joining, probation, and notice</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="ed-kv">
                            <div><span>Joining date</span><b>{{ $employee->joining_date?->format('d M Y') ?? '—' }}</b></div>
                            <div><span>Confirmation date</span><b>{{ $employee->confirmation_date?->format('d M Y') ?? '—' }}</b></div>
                            <div><span>Probation</span><b>{{ $employee->probation_days !== null ? $employee->probation_days.' days' : '—' }}</b></div>
                            <div><span>Notice period</span><b>{{ $employee->notice_period_days !== null ? $employee->notice_period_days.' days' : '—' }}</b></div>
                        </div>
                    </div>
                </section>

                @if($customFields->isNotEmpty())
                    <section class="card ef-sec">
                        <header>
                            <div>
                                <h3>Custom fields</h3>
                                <p>Extra details for this company</p>
                            </div>
                        </header>
                        <div class="cb">
                            <div class="ed-kv">
                                @foreach($customFields as $field)
                                    @php $current = optional($employee->customValues->firstWhere('employee_custom_field_id', $field->id))->value; @endphp
                                    <div><span>{{ $field->name }}</span><b>{{ $current ?: '—' }}</b></div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Movement history</h3>
                            <p>Transfers and status changes</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="ed-timeline">
                            @forelse($employee->events as $event)
                                <div class="ed-tl-item">
                                    <span class="ed-tl-dot"></span>
                                    <div>
                                        <b>{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</b>
                                        <small>{{ $event->effective_date->format('d M Y') }}</small>
                                        <p>{{ $event->reason }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="ed-empty">No transfers or status changes recorded.</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>

            <aside class="ed-side">
                @permission('employee_documents.view')
                <section class="card ef-sec" id="documents">
                    <header>
                        <div>
                            <h3>Documents</h3>
                            <p>Files linked to this person</p>
                        </div>
                        @permission('employee_documents.create')
                            <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#docUploadModal"><x-i name="plus" :size="14" />Add</button>
                        @endpermission
                    </header>
                    <div class="cb">
                        <div class="ed-docs">
                            @forelse($employee->documents as $document)
                                @php
                                    $mime = (string) ($document->mime ?? '');
                                    $isImage = str_starts_with($mime, 'image/');
                                    $isPdf = $mime === 'application/pdf' || str_ends_with(strtolower((string) $document->original_name), '.pdf');
                                    $sizeKb = $document->size ? round($document->size / 1024, 1).' KB' : null;
                                @endphp
                                <article class="ed-doc">
                                    <div class="ed-doc-preview">
                                        @if($isImage)
                                            <img src="{{ route('employees.documents.view', [$employee, $document]) }}" alt="{{ $document->title }}">
                                        @else
                                            <div class="ed-doc-fallback">
                                                <x-i name="file" :size="22" />
                                                <span>{{ $isPdf ? 'PDF' : strtoupper(pathinfo((string) $document->original_name, PATHINFO_EXTENSION) ?: 'FILE') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ed-doc-meta">
                                        <b>{{ $document->title }}</b>
                                        <small>{{ $document->original_name }}@if($sizeKb) · {{ $sizeKb }}@endif</small>
                                        <div class="el-ops">
                                            <button class="ib" type="button" title="View" aria-label="View"
                                                data-bs-toggle="modal" data-bs-target="#docViewModal"
                                                data-title="{{ $document->title }}"
                                                data-name="{{ $document->original_name }}"
                                                data-src="{{ route('employees.documents.view', [$employee, $document]) }}"
                                                data-kind="{{ $isImage ? 'image' : ($isPdf ? 'pdf' : 'file') }}"
                                                data-download="{{ route('employees.documents.download', [$employee, $document]) }}">
                                                <x-i name="eye" :size="14" />
                                            </button>
                                            <a class="ib" href="{{ route('employees.documents.download', [$employee, $document]) }}" title="Download" aria-label="Download"><x-i name="dl" :size="14" /></a>
                                            @permission('employee_documents.delete')
                                                <form method="post" action="{{ route('employees.documents.destroy', [$employee, $document]) }}" data-confirm="Delete this document?">
                                                    @csrf @method('delete')
                                                    <button class="ib er" type="submit" title="Delete" aria-label="Delete"><x-i name="trash" :size="14" /></button>
                                                </form>
                                            @endpermission
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <p class="ed-empty">No documents uploaded.</p>
                            @endforelse
                        </div>
                    </div>
                </section>
                @endpermission

                @permission('bank_accounts.view')
                <section class="card ef-sec" id="bank">
                    <header>
                        <div>
                            <h3>Bank accounts</h3>
                            <p>Payroll payout details</p>
                        </div>
                        @if($canManageBank ?? false)
                            @if($activeBank)
                                <button class="ib" type="button" title="Edit" aria-label="Edit" data-bs-toggle="modal" data-bs-target="#bankModal"><x-i name="pen" :size="15" /></button>
                            @else
                                <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#bankModal"><x-i name="plus" :size="14" />Add</button>
                            @endif
                        @endif
                    </header>
                    <div class="cb">
                        <div class="ed-stack">
                            @forelse($employee->bankAccounts as $account)
                                <div class="ed-bank {{ $account->is_active ? 'on' : '' }}">
                                    <div class="ed-bank-top">
                                        <b>{{ $account->bank_name }}</b>
                                        <span class="b {{ $account->is_active ? 'ok' : '' }}">{{ $account->is_active ? 'Active' : 'Historical' }}</span>
                                    </div>
                                    <span>{{ $account->masked_account }}</span>
                                    @if($canViewAccount)
                                        <small>Full number: {{ $account->account_number }}</small>
                                    @endif
                                    <small>{{ $account->ifsc }} · from {{ $account->effective_from->format('d M Y') }}</small>
                                    @if($account->beneficiary_name)
                                        <small>Beneficiary: {{ $account->beneficiary_name }}</small>
                                    @endif
                                </div>
                            @empty
                                <p class="ed-empty">No bank account recorded.</p>
                            @endforelse
                        </div>
                    </div>
                </section>
                @endpermission

                @permission('employee_exits.create')
                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Exit</h3>
                            <p>Resignation and final working dates</p>
                        </div>
                        @if($employee->latestExit)
                            <button class="ib" type="button" title="Edit" aria-label="Edit" data-bs-toggle="modal" data-bs-target="#exitModal"><x-i name="pen" :size="15" /></button>
                        @else
                            <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#exitModal"><x-i name="plus" :size="14" />Add</button>
                        @endif
                    </header>
                    <div class="cb">
                        @if($employee->latestExit)
                            <div class="ed-kv ed-kv-1">
                                <div><span>Resignation</span><b>{{ $employee->latestExit->resignation_date?->format('d M Y') ?? '—' }}</b></div>
                                <div><span>Last working day</span><b>{{ $employee->latestExit->last_working_date?->format('d M Y') ?? '—' }}</b></div>
                                <div><span>Reason</span><b>{{ $employee->latestExit->reason ?: '—' }}</b></div>
                                <div><span>Status</span><b>{{ $employee->latestExit->employmentStatus?->name ?? '—' }}</b></div>
                                <div><span>FnF</span><b>{{ str_replace('_', ' ', ucfirst($employee->latestExit->fnf_status ?? '—')) }}</b></div>
                                @if($employee->latestExit->notes)
                                    <div><span>Notes</span><b>{{ $employee->latestExit->notes }}</b></div>
                                @endif
                            </div>
                        @else
                            <p class="ed-empty">No exit recorded. Use Add to capture resignation details.</p>
                        @endif
                    </div>
                </section>
                @endpermission
            </aside>
        </div>
    </div>

    @permission('employee_documents.create')
    <div class="modal fade" id="docUploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('employees.documents.store', $employee) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Upload document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <x-text-field name="title" label="Title" required />
                    <x-text-field name="document" label="File" type="file" required />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn pr" type="submit">Upload</button>
                </div>
            </form>
        </div>
    </div>
    @endpermission

    @permission('employee_documents.view')
    <div class="modal fade" id="docViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="docViewTitle">Document</h5>
                        <small class="ed-muted" id="docViewName"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body ed-viewer">
                    <img id="docViewImage" alt="" hidden>
                    <iframe id="docViewFrame" title="Document preview" hidden></iframe>
                    <div id="docViewFile" class="ed-viewer-file" hidden>
                        <x-i name="file" :size="28" />
                        <p>Preview is not available for this file type.</p>
                        <a class="btn pr" id="docViewDownload" href="#">Download file</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpermission

    @if($canManageBank ?? false)
    <div class="modal fade" id="bankModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('employees.bank-accounts.store', $employee) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ $activeBank ? 'Edit bank account' : 'Add bank account' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($activeBank)
                        <p class="ef-hint">Saving creates the new active account and keeps the previous one in history.</p>
                    @endif
                    <x-text-field name="beneficiary_name" label="Beneficiary" :value="$activeBank?->beneficiary_name" required />
                    <x-text-field name="bank_name" label="Bank" :value="$activeBank?->bank_name" required />
                    <x-text-field name="account_number" label="Account number" :value="$activeBank?->account_number" required />
                    <x-text-field name="ifsc" label="IFSC" :value="$activeBank?->ifsc" required />
                    <x-text-field name="effective_from" label="Effective from" type="date" :value="optional($activeBank?->effective_from)->toDateString() ?: now()->toDateString()" required />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn pr" type="submit">{{ $activeBank ? 'Save changes' : 'Save account' }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @permission('employee_exits.create')
    <div class="modal fade" id="exitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('employees.exit.store', $employee) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ $employee->latestExit ? 'Edit exit' : 'Record exit' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="ef-hint">Salary settlement is calculated in payroll, not on this screen.</p>
                    <x-text-field name="resignation_date" label="Resignation date" type="date" :value="optional($employee->latestExit?->resignation_date)->toDateString()" required />
                    <x-text-field name="last_working_date" label="Last working date" type="date" :value="optional($employee->latestExit?->last_working_date)->toDateString()" required />
                    <x-text-field name="reason" label="Reason" :value="$employee->latestExit?->reason" required />
                    <x-select-field name="employment_status_id" label="Exit status" required>
                        <option value="">Select</option>
                        @foreach($exitStatuses as $exitStatus)
                            <option value="{{ $exitStatus->id }}" @selected(old('employment_status_id', $employee->latestExit?->employment_status_id) == $exitStatus->id)>{{ $exitStatus->name }}</option>
                        @endforeach
                    </x-select-field>
                    <x-select-field name="fnf_status" label="Full and final status" required>
                        @foreach(['not_started' => 'Not started', 'pending' => 'Pending', 'completed' => 'Completed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('fnf_status', $employee->latestExit?->fnf_status ?? 'not_started') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select-field>
                    <x-text-field name="notes" label="Notes" type="textarea" :value="$employee->latestExit?->notes" />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn pr" type="submit">Save exit</button>
                </div>
            </form>
        </div>
    </div>
    @endpermission
@endsection

@push('scripts')
<script>
(function () {
    const viewModal = document.getElementById('docViewModal');
    if (!viewModal) return;

    const title = document.getElementById('docViewTitle');
    const name = document.getElementById('docViewName');
    const image = document.getElementById('docViewImage');
    const frame = document.getElementById('docViewFrame');
    const fileBox = document.getElementById('docViewFile');
    const download = document.getElementById('docViewDownload');

    viewModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        const kind = btn.getAttribute('data-kind');
        const src = btn.getAttribute('data-src');
        title.textContent = btn.getAttribute('data-title') || 'Document';
        name.textContent = btn.getAttribute('data-name') || '';
        download.href = btn.getAttribute('data-download') || '#';

        image.hidden = true;
        frame.hidden = true;
        fileBox.hidden = true;
        image.removeAttribute('src');
        frame.removeAttribute('src');

        if (kind === 'image') {
            image.src = src;
            image.alt = title.textContent;
            image.hidden = false;
        } else if (kind === 'pdf') {
            frame.src = src;
            frame.hidden = false;
        } else {
            fileBox.hidden = false;
        }
    });

    viewModal.addEventListener('hidden.bs.modal', function () {
        image.removeAttribute('src');
        frame.removeAttribute('src');
    });
})();
</script>
@endpush
