@extends('layouts.app')
@section('title', 'Bank Transfers')
@section('content')
    <div class="ph">
        <div>
            <h1>Bank Transfers</h1>
            <p>Build a file from an approved payroll. Downloading it does not send money.</p>
        </div>
    </div>
    @include('operations.errors')
    @if($canEdit)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Company account</h3>
            <form method="post" action="{{ route('bank.accounts.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px">
                @csrf
                <label>Label<br><input class="in" name="label" required></label>
                <label>Bank<br><input class="in" name="bank_name" required></label>
                <label>Account number<br><input class="in" name="account_number" required autocomplete="off"></label>
                <label>IFSC<br><input class="in" name="ifsc" required></label>
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="is_primary" value="1"> Primary</label>
                <button class="btn pr" type="submit">Save account</button>
            </form>
        </section>
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Payment file</h3>
            <form method="post" action="{{ route('bank.batches.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px">
                @csrf
                <label>Approved payroll<br>
                    <select class="in" name="payroll_run_id" required>
                        <option value="">Select</option>
                        @foreach($runs as $run)
                            <option value="{{ $run->id }}">{{ \Carbon\Carbon::create($run->period_year, $run->period_month, 1)->format('F Y') }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Pay from<br>
                    <select class="in" name="company_bank_account_id" required>
                        <option value="">Select</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->label }} · {{ $account->masked_account }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Date on the file<br><input class="in" type="date" name="scheduled_on" required></label>
                <button class="btn pr" type="submit">Prepare file</button>
            </form>
        </section>
    @endif
    <section class="card" style="margin-bottom:16px">
        <header>Accounts</header>
        <div class="tw">
            <table>
                <thead><tr><th>Label</th><th>Bank</th><th>Account</th><th>IFSC</th><th></th></tr></thead>
                <tbody>
                @forelse($accounts as $account)
                    <tr>
                        <td>{{ $account->label }}</td>
                        <td>{{ $account->bank_name }}</td>
                        <td>{{ $account->masked_account }}</td>
                        <td>{{ $account->ifsc }}</td>
                        <td>@if($account->is_primary)<span class="b ok">Primary</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No company account yet</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <header>Files</header>
        <div class="tw">
            <table>
                <thead><tr><th>Period</th><th>Account</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td>{{ $batch->run ? \Carbon\Carbon::create($batch->run->period_year, $batch->run->period_month, 1)->format('M Y') : '—' }}</td>
                        <td>{{ $batch->account?->label }}</td>
                        <td>{{ $batch->scheduled_on->format('d M Y') }}</td>
                        <td>₹{{ number_format((float) $batch->total, 2) }}</td>
                        <td><span class="b {{ $batch->status === 'exported' ? 'ok' : 'wn' }}">{{ $batch->status }}</span></td>
                        <td>@if($canEdit)<a class="btn" href="{{ route('bank.export', $batch) }}">Download CSV</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--mu);padding:32px">No payment files</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
