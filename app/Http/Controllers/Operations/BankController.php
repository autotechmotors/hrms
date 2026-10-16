<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\CompanyBankAccount;
use App\Models\PaymentBatch;
use App\Models\PayrollRun;
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
            $user->hasPermission('bank_accounts.view')
            || $user->hasPermission('bank_accounts.manage')
            || $user->hasPermission('employees.view')
            || $user->hasPermission('employees.edit'),
            403,
        );
        $companyId = $this->companyId();

        return view('operations.bank.index', [
            'accounts' => CompanyBankAccount::query()->visibleTo($user)->where('company_id', $companyId)->orderByDesc('is_primary')->get(),
            'batches' => PaymentBatch::query()->visibleTo($user)->where('company_id', $companyId)->with(['run', 'account'])->latest()->get(),
            'runs' => PayrollRun::query()->visibleTo($user)->where('company_id', $companyId)->where('status', 'approved')->latest('period_year')->get(),
            'canEdit' => $user->hasPermission('bank_accounts.manage') || $user->hasPermission('employees.edit') || $user->hasPermission('payments.release'),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizeBank();
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'min:6', 'max:30'],
            'ifsc' => ['required', 'string', 'max:20'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
        $companyId = $this->companyId();
        if ($request->boolean('is_primary')) {
            CompanyBankAccount::query()->where('company_id', $companyId)->update(['is_primary' => false]);
        }

        CompanyBankAccount::query()->create([
            'company_id' => $companyId,
            'label' => $data['label'],
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'account_last4' => substr(preg_replace('/\D/', '', $data['account_number']) ?: $data['account_number'], -4),
            'ifsc' => strtoupper($data['ifsc']),
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return back()->with('success', 'Company bank account saved. The full number is stored encrypted.');
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $this->authorizeBank();
        $companyId = $this->companyId();
        $data = $request->validate([
            'payroll_run_id' => ['required', 'integer'],
            'company_bank_account_id' => ['required', 'integer'],
            'scheduled_on' => ['required', 'date'],
        ]);
        $run = PayrollRun::query()->visibleTo($request->user())->where('company_id', $companyId)->where('status', 'approved')->findOrFail($data['payroll_run_id']);
        $account = CompanyBankAccount::query()->visibleTo($request->user())->where('company_id', $companyId)->findOrFail($data['company_bank_account_id']);
        $run->load('items.employee.activeBankAccount');

        $batch = PaymentBatch::query()->create([
            'company_id' => $companyId,
            'payroll_run_id' => $run->id,
            'company_bank_account_id' => $account->id,
            'scheduled_on' => $data['scheduled_on'],
            'status' => 'draft',
            'total' => 0,
        ]);
        $total = 0;
        foreach ($run->items as $item) {
            $amount = (float) $item->net;
            $total += $amount;
            $batch->lines()->create([
                'employee_id' => $item->employee_id,
                'amount' => $amount,
                'account_last4' => $item->employee?->activeBankAccount?->account_last4,
            ]);
        }
        $batch->update(['total' => round($total, 2)]);

        return back()->with('success', 'Payment file prepared. Download it for the bank. This screen does not transfer money.');
    }

    public function export(PaymentBatch $batch): StreamedResponse
    {
        $this->authorizeBank();
        abort_unless((int) $batch->company_id === $this->companyId(), 404);
        $batch->load(['lines.employee.activeBankAccount', 'account', 'run']);
        $batch->update(['status' => 'exported']);

        $filename = 'payroll-'.$batch->run->period_year.'-'.str_pad((string) $batch->run->period_month, 2, '0', STR_PAD_LEFT).'.csv';

        return response()->streamDownload(function () use ($batch): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Schedule only. No payment is sent from HRMS.']);
            fputcsv($out, ['From account', $batch->account->label, $batch->account->masked_account, $batch->account->ifsc]);
            fputcsv($out, ['Scheduled on', $batch->scheduled_on->toDateString()]);
            fputcsv($out, []);
            fputcsv($out, ['Employee code', 'Name', 'Amount INR', 'Account last 4', 'IFSC', 'Note']);
            foreach ($batch->lines as $line) {
                $bank = $line->employee?->activeBankAccount;
                fputcsv($out, [
                    $line->employee?->employee_code,
                    $line->employee?->full_name,
                    $line->amount,
                    $line->account_last4,
                    $bank?->ifsc,
                    $line->account_last4 ? '' : 'No active employee bank account',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
