@extends('layouts.app')
@section('title', 'Salary Structures')
@section('content')
    @php
        $methodLabels = [
            'fixed' => 'Fixed amount',
            'percent_gross' => '% of gross',
            'percent_basic' => '% of basic',
        ];
    @endphp

    <div class="ph">
        <div>
            <h1>Salary Structures</h1>
            <p>Build heads, group them into structures, then assign monthly gross to employees.</p>
        </div>
        <div class="ac">
            @if($canEdit)
                <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#salaryHeadModal">
                    <x-i name="plus" :size="14" />Add head
                </button>
                <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#salaryStructureModal" @disabled($heads->isEmpty())>
                    <x-i name="plus" :size="14" />New structure
                </button>
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#salaryAssignModal" @disabled($structures->isEmpty())>
                    <x-i name="plus" :size="14" />Assign
                </button>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <div class="sa-stats g c4">
        <div class="card kp">
            <small><span class="sa-dot heads"></span>Heads</small>
            <b>{{ $stats['heads'] }}</b>
            <span>{{ $stats['earnings'] }} earning · {{ $stats['deductions'] }} deduction</span>
        </div>
        <div class="card kp">
            <small><span class="sa-dot structures"></span>Structures</small>
            <b>{{ $stats['structures'] }}</b>
            <span>Reusable pay packages</span>
        </div>
        <div class="card kp">
            <small><span class="sa-dot assign"></span>Assignments</small>
            <b>{{ $stats['assignments'] }}</b>
            <span>Shown below (latest 50)</span>
        </div>
        <div class="card kp">
            <small><span class="sa-dot tip"></span>Tip</small>
            <b class="sa-tip">BASIC</b>
            <span>Use this code for percent-of-basic</span>
        </div>
    </div>

    <div class="sa-layout">
        <section class="card sa-panel">
            <header>
                <div>
                    <h3>Salary heads</h3>
                    <p>Building blocks for earnings and deductions.</p>
                </div>
                @if($canEdit)
                    <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#salaryHeadModal">
                        <x-i name="plus" :size="12" />New
                    </button>
                @endif
            </header>
            <div class="cb">
                @if($heads->isEmpty())
                    <p class="sa-empty">No heads yet. Add Basic first, then HRA, PF, and others.</p>
                @else
                    <div class="sa-chips">
                        @foreach($heads as $head)
                            <article class="sa-chip {{ $head->category === 'deduction' ? 'is-deduction' : 'is-earning' }}">
                                <div class="sa-chip-code">{{ $head->code }}</div>
                                <div class="sa-chip-body">
                                    <div class="sa-chip-top">
                                        <b title="{{ $head->name }}">{{ $head->name }}</b>
                                        <span class="b {{ $head->category === 'earning' ? 'ok' : 'wn' }}">{{ ucfirst($head->category) }}</span>
                                    </div>
                                    <div class="sa-chip-meta">
                                        <span>{{ $methodLabels[$head->method] ?? $head->method }}</span>
                                        <span class="sa-sep">·</span>
                                        <span>{{ $head->method === 'fixed' ? '₹'.number_format((float) $head->value, 2) : rtrim(rtrim(number_format((float) $head->value, 2), '0'), '.').'%' }}</span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="card sa-panel">
            <header>
                <div>
                    <h3>Structures</h3>
                    <p>Packages that combine selected heads.</p>
                </div>
                @if($canEdit)
                    <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#salaryStructureModal" @disabled($heads->isEmpty())>
                        <x-i name="plus" :size="12" />New
                    </button>
                @endif
            </header>
            <div class="cb">
                @if($structures->isEmpty())
                    <p class="sa-empty">No structures yet. Create one after you have heads.</p>
                @else
                    <div class="sa-structures">
                        @foreach($structures as $structure)
                            @php
                                $codes = $structure->lines->map(fn ($line) => $line->head?->code)->filter();
                            @endphp
                            <article class="sa-structure">
                                <div class="sa-structure-top">
                                    <span class="sa-structure-code">{{ $structure->code }}</span>
                                    <b>{{ $structure->name }}</b>
                                </div>
                                <div class="sa-structure-heads">
                                    @forelse($codes as $code)
                                        <span class="sa-pill">{{ $code }}</span>
                                    @empty
                                        <span class="sa-muted">No heads</span>
                                    @endforelse
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    <section class="card sa" id="salaryAssignments">
        <form class="tbar sa-bar" method="get" action="{{ route('salary.index') }}" id="salaryFilters">
            <div class="sr sa-search">
                <span><x-i name="search" /></span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search employee…" autocomplete="off">
            </div>
            <a class="btn sa-reset" href="{{ route('salary.index') }}" title="Reset">
                <x-i name="reset" :size="14" />Reset
            </a>
            <div class="sp"></div>
            @if($canEdit)
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#salaryAssignModal" @disabled($structures->isEmpty())>
                    <x-i name="plus" :size="14" />Assign gross
                </button>
            @endif
            <span class="sa-meta">{{ $assignments->count() }} {{ \Illuminate\Support\Str::plural('assignment', $assignments->count()) }}</span>
        </form>

        <div class="tw sa-table">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Structure</th>
                        <th>Gross / month</th>
                        <th>Effective from</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($assignments as $row)
                    <tr>
                        <td>
                            @if($row->employee)
                                <div class="who">
                                    <x-employee-avatar :employee="$row->employee" :size="32" />
                                    <div>
                                        <b>{{ $row->employee->full_name }}</b>
                                        <small>{{ $row->employee->employee_code }}@if($row->employee->department) · {{ $row->employee->department->name }}@endif</small>
                                    </div>
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="sa-structure-code sm">{{ $row->structure?->code ?? '—' }}</span>
                            <small class="sa-sub">{{ $row->structure?->name }}</small>
                        </td>
                        <td class="sa-gross">₹{{ number_format((float) $row->gross_monthly, 2) }}</td>
                        <td>{{ $row->effective_from->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="sa-empty-row">No assignments match. Assign a structure and monthly gross to an employee.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($canEdit)
        <div class="modal fade" id="salaryHeadModal" tabindex="-1" aria-labelledby="salaryHeadTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('salary.heads.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="salaryHeadTitle">Add salary head</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body sa-modal-body">
                        <p class="sa-modal-help">Code BASIC is special — percent-of-basic methods use that head.</p>
                        <div class="sa-modal-grid">
                            <div>
                                <label class="form-label">Name *</label>
                                <input class="in w-100" name="name" required value="{{ old('name') }}" placeholder="Basic">
                            </div>
                            <div>
                                <label class="form-label">Code *</label>
                                <input class="in w-100" name="code" required value="{{ old('code', 'BASIC') }}" placeholder="BASIC" maxlength="40">
                            </div>
                        </div>
                        <div class="sa-modal-grid">
                            <div>
                                <label class="form-label">Category *</label>
                                <select class="in w-100" name="category">
                                    <option value="earning" @selected(old('category', 'earning') === 'earning')>Earning</option>
                                    <option value="deduction" @selected(old('category') === 'deduction')>Deduction</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Method *</label>
                                <select class="in w-100" name="method">
                                    <option value="fixed" @selected(old('method', 'fixed') === 'fixed')>Fixed amount</option>
                                    <option value="percent_gross" @selected(old('method') === 'percent_gross')>Percent of gross</option>
                                    <option value="percent_basic" @selected(old('method') === 'percent_basic')>Percent of basic</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Value *</label>
                            <input class="in w-100" type="number" step="0.01" min="0" name="value" required value="{{ old('value') }}" placeholder="100 or amount">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr">Save head</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="salaryStructureModal" tabindex="-1" aria-labelledby="salaryStructureTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('salary.structures.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="salaryStructureTitle">New structure</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body sa-modal-body">
                        <div class="sa-modal-grid">
                            <div>
                                <label class="form-label">Name *</label>
                                <input class="in w-100" name="name" required value="{{ old('name') }}" placeholder="Standard">
                            </div>
                            <div>
                                <label class="form-label">Code *</label>
                                <input class="in w-100" name="code" required value="{{ old('code') }}" placeholder="STD" maxlength="40">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Include heads *</label>
                            <div class="sa-head-picks">
                                @forelse($heads as $head)
                                    <label class="sa-head-pick">
                                        <input type="checkbox" name="head_ids[]" value="{{ $head->id }}" @checked(collect(old('head_ids', []))->contains($head->id))>
                                        <span class="sa-pill">{{ $head->code }}</span>
                                        <span>{{ $head->name }}</span>
                                    </label>
                                @empty
                                    <p class="sa-empty">Add a head first.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr" @disabled($heads->isEmpty())>Save structure</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="salaryAssignModal" tabindex="-1" aria-labelledby="salaryAssignTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('salary.assign') }}">
                    @csrf
                    @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                    <div class="modal-header">
                        <h5 class="modal-title" id="salaryAssignTitle">Assign monthly gross</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body sa-modal-body">
                        <p class="sa-modal-help">Payroll picks the assignment effective on the pay month.</p>
                        <label class="form-label">Employee *</label>
                        <select class="in w-100" name="employee_id" required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->employee_code }} — {{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <label class="form-label">Structure *</label>
                        <select class="in w-100" name="salary_structure_id" required>
                            <option value="">Select structure</option>
                            @foreach($structures as $structure)
                                <option value="{{ $structure->id }}" @selected(old('salary_structure_id') == $structure->id)>{{ $structure->code }} — {{ $structure->name }}</option>
                            @endforeach
                        </select>
                        <div class="sa-modal-grid">
                            <div>
                                <label class="form-label">Gross / month *</label>
                                <input class="in w-100" type="number" step="0.01" min="0" name="gross_monthly" required value="{{ old('gross_monthly') }}" placeholder="30000">
                            </div>
                            <div>
                                <label class="form-label">Effective from *</label>
                                <input class="in w-100" type="date" name="effective_from" required value="{{ old('effective_from') }}">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function ($) {
    const form = $('#salaryFilters');
    if (!form.length) return;
    let timer = null;
    form.on('input', 'input[name="q"]', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.trigger('submit'); }, 350);
    });
})(jQuery);
</script>
@endpush
