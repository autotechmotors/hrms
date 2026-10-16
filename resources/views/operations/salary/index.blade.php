@extends('layouts.app')
@section('title', 'Salary Structures')
@section('content')
    <div class="ph">
        <div>
            <h1>Salary Structures</h1>
            <p>Heads and structures are empty until you add them. Percent of basic uses the head coded BASIC.</p>
        </div>
    </div>
    @include('operations.errors')
    @if($canEdit)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Salary head</h3>
            <form method="post" action="{{ route('salary.heads.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px">
                @csrf
                <label>Name<br><input class="in" name="name" required></label>
                <label>Code<br><input class="in" name="code" required placeholder="BASIC"></label>
                <label>Category<br>
                    <select class="in" name="category"><option value="earning">Earning</option><option value="deduction">Deduction</option></select>
                </label>
                <label>Method<br>
                    <select class="in" name="method">
                        <option value="fixed">Fixed amount</option>
                        <option value="percent_gross">Percent of gross</option>
                        <option value="percent_basic">Percent of basic</option>
                    </select>
                </label>
                <label>Value<br><input class="in" type="number" step="0.01" min="0" name="value" required></label>
                <button class="btn pr" type="submit">Add head</button>
            </form>
        </section>
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Structure</h3>
            <form method="post" action="{{ route('salary.structures.store') }}" style="margin-top:10px">
                @csrf
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                    <label>Name<br><input class="in" name="name" required></label>
                    <label>Code<br><input class="in" name="code" required></label>
                    <button class="btn pr" type="submit">Save structure</button>
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px">
                    @forelse($heads as $head)
                        <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="head_ids[]" value="{{ $head->id }}"> {{ $head->name }} ({{ $head->code }})</label>
                    @empty
                        <span style="color:var(--mu)">Add a head first.</span>
                    @endforelse
                </div>
            </form>
        </section>
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Assign monthly gross</h3>
            <form method="post" action="{{ route('salary.assign') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px">
                @csrf
                <label>Employee<br>
                    <select class="in" name="employee_id" required>
                        <option value="">Select</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Structure<br>
                    <select class="in" name="salary_structure_id" required>
                        <option value="">Select</option>
                        @foreach($structures as $structure)
                            <option value="{{ $structure->id }}">{{ $structure->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Gross / month<br><input class="in" type="number" step="0.01" min="0" name="gross_monthly" required></label>
                <label>Effective from<br><input class="in" type="date" name="effective_from" required></label>
                <button class="btn pr" type="submit">Assign</button>
            </form>
        </section>
    @endif
    <section class="card" style="margin-bottom:16px">
        <header>Heads</header>
        <div class="tw">
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Method</th><th>Value</th></tr></thead>
                <tbody>
                @forelse($heads as $head)
                    <tr>
                        <td>{{ $head->code }}</td>
                        <td>{{ $head->name }}</td>
                        <td>{{ $head->category }}</td>
                        <td>{{ str_replace('_', ' ', $head->method) }}</td>
                        <td>{{ $head->value }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No salary heads</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card" style="margin-bottom:16px">
        <header>Structures</header>
        <div class="tw">
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Heads</th></tr></thead>
                <tbody>
                @forelse($structures as $structure)
                    <tr>
                        <td>{{ $structure->code }}</td>
                        <td>{{ $structure->name }}</td>
                        <td>{{ $structure->lines->map(fn ($line) => $line->head?->code)->filter()->join(', ') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:var(--mu);padding:32px">No structures</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <header>Assignments</header>
        <div class="tw">
            <table>
                <thead><tr><th>Employee</th><th>Structure</th><th>Gross</th><th>From</th></tr></thead>
                <tbody>
                @forelse($assignments as $row)
                    <tr>
                        <td>{{ $row->employee?->full_name }}</td>
                        <td>{{ $row->structure?->name }}</td>
                        <td>₹{{ number_format((float) $row->gross_monthly, 2) }}</td>
                        <td>{{ $row->effective_from->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--mu);padding:32px">No one has a gross assigned</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
