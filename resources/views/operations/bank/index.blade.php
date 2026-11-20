@extends('layouts.app')
@section('title', 'Bank Transfers')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Bank Transfers') }}</h1>
            <p>{{ __('One file pays every person on an approved salary sheet. Each line is that person\'s salary net, from the company account to their own account. Upload the file to the bank.') }}</p>
        </div>
        @if($canEdit)
            <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#companyAccountModal">
                <x-i name="plus" :size="14" /> {{ __('Add company account') }}
            </button>
        @endif
    </div>
    @include('operations.errors')
    @if($canEdit)
        <section class="card" style="margin-bottom:16px">
            <header>{{ __('Bulk payment file') }}</header>
            <div class="cb">
                <p class="mu" style="margin:0 0 12px">{{ __('The amount on each line is that person\'s salary net.') }}</p>
                <form class="g c3 bk-form" method="post" action="{{ route('bank.batches.store') }}">
                    @csrf
                    <x-select-field name="salary_sheet_id" label="Approved salary sheet" required>
                        <option value="">{{ __('Select') }}</option>
                        @foreach($sheets as $sheet)
                            <option value="{{ $sheet->id }}" @selected((string) old('salary_sheet_id') === (string) $sheet->id)>{{ $sheet->title }} · {{ $sheet->starts_on->format('d M') }}–{{ $sheet->ends_on->format('d M Y') }}</option>
                        @endforeach
                    </x-select-field>
                    <x-select-field name="company_bank_account_id" label="Pay from" required>
                        <option value="">{{ __('Select') }}</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('company_bank_account_id') === (string) $account->id)>{{ $account->label }} · {{ $account->masked_account }}@if($account->is_primary) · {{ __('Primary') }}@endif</option>
                        @endforeach
                    </x-select-field>
                    <x-text-field name="scheduled_on" label="Date on the file" type="date" required />
                    <div><button class="btn pr" type="submit">{{ __('Prepare bulk file') }}</button></div>
                </form>
            </div>
        </section>
    @endif
    <section class="card" style="margin-bottom:16px">
        <header>Accounts</header>
        <div class="tw">
            <table>
                <thead><tr><th>{{ __('Label') }}</th><th>{{ __('Account holder') }}</th><th>{{ __('Bank') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Account') }}</th><th>{{ __('IFSC') }}</th><th>{{ __('GST') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($accounts as $account)
                    <tr>
                        <td>{{ $account->label }}</td>
                        <td>{{ $account->account_holder }}</td>
                        <td>{{ $account->bank_name }}</td>
                        <td>{{ $account->branch_name }}</td>
                        <td>{{ $account->masked_account }}</td>
                        <td>{{ $account->ifsc }}</td>
                        <td>{{ $account->gst_number ?: '—' }}</td>
                        <td>@if($account->is_primary)<span class="b ok">Primary</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--mu);padding:32px">No company account yet</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <header>Files</header>
        <div class="tw">
            <table>
                <thead><tr><th>{{ __('Period') }}</th><th>{{ __('People') }}</th><th>{{ __('Account') }}</th><th>{{ __('Date') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td>{{ $batch->sheet ? $batch->sheet->starts_on->format('d M').'–'.$batch->sheet->ends_on->format('d M Y') : ($batch->run ? \Carbon\Carbon::create($batch->run->period_year, $batch->run->period_month, 1)->format('M Y') : '—') }}</td>
                        <td>{{ $batch->lines_count }}</td>
                        <td>{{ $batch->account?->label }}</td>
                        <td>{{ $batch->scheduled_on->format('d M Y') }}</td>
                        <td>₹{{ number_format((float) $batch->total, 2) }}</td>
                        <td><span class="b {{ $batch->status === 'exported' ? 'ok' : 'wn' }}">{{ $batch->status }}</span></td>
                        <td>@if($canEdit)<a class="btn" href="{{ route('bank.export', $batch) }}">Download CSV</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;color:var(--mu);padding:32px">No payment files</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @if($canEdit)
        <div class="modal fade" id="companyAccountModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('bank.accounts.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Add company account') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <x-text-field name="label" label="Label" required />
                        <x-text-field name="account_holder" label="Account holder" required />
                        <x-text-field name="bank_name" label="Bank" required />
                        <x-text-field name="branch_name" label="Branch" />
                        <x-text-field name="account_number" label="Account number" required autocomplete="off" />
                        <x-text-field name="ifsc" label="IFSC" required />
                        <x-text-field name="gst_number" label="GST number" maxlength="15" />
                        <p class="mu" style="margin:-6px 0 10px">{{ __('GST number is optional.') }}</p>
                        <x-check-field name="is_primary" label="Primary account" :checked="$accounts->where('is_primary', true)->isEmpty()" />
                        <p class="mu" style="margin-top:-6px">{{ __('Only one company account stays primary. Saving this as primary clears the others.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn pr" type="submit">{{ __('Save account') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @if($errors->hasAny(['label', 'account_holder', 'bank_name', 'branch_name', 'account_number', 'ifsc', 'gst_number']))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var modal = document.getElementById('companyAccountModal');
                    if (modal && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modal).show();
                });
            </script>
        @endif
    @endif
@endsection
