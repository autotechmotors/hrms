<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\CompanyBankAccount;
use App\Models\PaymentBatch;
use App\Models\SalarySheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('bank.view')
            || $user->hasPermission('bank_accounts.view')
            || $user->hasPermission('bank_accounts.manage')
            || $user->hasPermission('payments.release'),
            403,
        );
        $companyId = $this->companyId();
        $this->keepOnePrimary($companyId);

        return view('operations.bank.index', [
            'accounts' => CompanyBankAccount::query()->visibleTo($user)->where('company_id', $companyId)->orderByDesc('is_primary')->get(),
            'batches' => PaymentBatch::query()->visibleTo($user)->where('company_id', $companyId)->with(['run', 'sheet', 'account'])->withCount('lines')->latest()->get(),
            'sheets' => SalarySheet::query()->where('company_id', $companyId)->where('status', 'approved')->latest('id')->get(),
            'canEdit' => $user->hasPermission('bank_accounts.manage') || $user->hasPermission('payments.release'),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizeBank();
        $gst = strtoupper(preg_replace('/\s+/', '', $request->string('gst_number')->toString()) ?? '');
        $request->merge([
            'gst_number' => $gst === '' ? null : $gst,
            'ifsc' => strtoupper(trim($request->string('ifsc')->toString())),
        ]);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'account_holder' => ['required', 'string', 'max:120'],
            'bank_name' => ['required', 'string', 'max:120'],
            'branch_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'min:6', 'max:30'],
            'ifsc' => ['required', 'string', 'max:20'],
            'gst_number' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][A-Z0-9]Z[A-Z0-9]$/'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
        $companyId = $this->companyId();
        $makePrimary = $request->boolean('is_primary')
            || ! CompanyBankAccount::query()->where('company_id', $companyId)->where('is_primary', true)->exists();
        if ($makePrimary) {
            CompanyBankAccount::query()->where('company_id', $companyId)->update(['is_primary' => false]);
        }

        CompanyBankAccount::query()->create([
            'company_id' => $companyId,
            'label' => $data['label'],
            'account_holder' => $data['account_holder'],
            'bank_name' => $data['bank_name'],
            'branch_name' => $data['branch_name'] ?? null,
            'account_number' => $data['account_number'],
            'account_last4' => substr(preg_replace('/\D/', '', $data['account_number']) ?: $data['account_number'], -4),
            'ifsc' => $data['ifsc'],
            'gst_number' => $data['gst_number'] ?? null,
            'is_primary' => $makePrimary,
        ]);
        $this->keepOnePrimary($companyId);

        return back()->with('success', 'Company bank account saved. The full number is stored encrypted.');
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $this->authorizeBank();
        $companyId = $this->companyId();
        $data = $request->validate([
            'salary_sheet_id' => ['required', 'integer'],
            'company_bank_account_id' => ['required', 'integer'],
            'scheduled_on' => ['required', 'date'],
        ]);
        $sheet = SalarySheet::query()->where('company_id', $companyId)->where('status', 'approved')->findOrFail($data['salary_sheet_id']);
        $account = CompanyBankAccount::query()->visibleTo($request->user())->where('company_id', $companyId)->findOrFail($data['company_bank_account_id']);
        $sheet->load('lines.employee.activeBankAccount');

        $batch = PaymentBatch::query()->create([
            'company_id' => $companyId,
            'salary_sheet_id' => $sheet->id,
            'company_bank_account_id' => $account->id,
            'scheduled_on' => $data['scheduled_on'],
            'status' => 'draft',
            'total' => 0,
        ]);
        $total = 0;
        foreach ($sheet->lines as $line) {
            $amount = (float) $line->net;
            $total += $amount;
            $batch->lines()->create([
                'employee_id' => $line->employee_id,
                'amount' => $amount,
                'account_last4' => $line->employee?->activeBankAccount?->account_last4,
            ]);
        }
        $batch->update(['total' => round($total, 2)]);

        return back()->with('success', 'Bulk payment file prepared. Each line is that person\'s salary net, from the company account to their account. Download it and upload it to the bank.');
    }

    public function export(PaymentBatch $batch): StreamedResponse
    {
        $this->authorizeBank();
        abort_unless((int) $batch->company_id === $this->companyId(), 404);
        $batch->load(['lines.employee.activeBankAccount', 'account', 'run', 'sheet']);
        $batch->update(['status' => 'exported']);

        $filename = $batch->sheet
            ? 'salary-'.$batch->sheet->starts_on->format('Y-m-d').'.csv'
            : 'salary-'.($batch->run?->period_year ?? 'file').'-'.str_pad((string) ($batch->run?->period_month ?? 0), 2, '0', STR_PAD_LEFT).'.csv';

        return response()->streamDownload(function () use ($batch): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Bulk salary transfer. Each amount is that person\'s salary net, from the company account to their account. Upload this file to the bank.']);
            fputcsv($out, ['From account', $batch->account->label, $batch->account->masked_account, $batch->account->ifsc]);
            fputcsv($out, ['Account holder', $batch->account->account_holder]);
            fputcsv($out, ['Branch', $batch->account->branch_name]);
            fputcsv($out, ['GST', $batch->account->gst_number]);
            fputcsv($out, ['Scheduled on', $batch->scheduled_on->toDateString()]);
            fputcsv($out, []);
            fputcsv($out, ['Employee code', 'Name', 'Amount INR', 'Account last 4', 'IFSC', 'Note', 'Beneficiary', 'Account number', 'Branch', 'Debit account', 'Debit IFSC']);
            $debit = $batch->account->account_number;
            foreach ($batch->lines as $line) {
                $bank = $line->employee?->activeBankAccount;
                fputcsv($out, [
                    $line->employee?->employee_code,
                    $line->employee?->full_name,
                    $line->amount,
                    $line->account_last4,
                    $bank?->ifsc,
                    $line->account_last4 ? '' : 'No active employee bank account',
                    $bank?->beneficiary_name,
                    $bank?->account_number,
                    $bank?->branch_name,
                    $debit,
                    $batch->account->ifsc,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function keepOnePrimary(int $companyId): void
    {
        $primary = CompanyBankAccount::query()
            ->where('company_id', $companyId)
            ->where('is_primary', true)
            ->orderBy('id')
            ->pluck('id');

        if ($primary->count() === 1) {
            return;
        }

        $keep = $primary->first()
            ?? CompanyBankAccount::query()->where('company_id', $companyId)->orderBy('id')->value('id');

        if (! $keep) {
            return;
        }

        CompanyBankAccount::query()->where('company_id', $companyId)->whereKeyNot($keep)->update(['is_primary' => false]);
        CompanyBankAccount::query()->whereKey($keep)->update(['is_primary' => true]);
    }
}
