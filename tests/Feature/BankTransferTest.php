<?php

namespace Tests\Feature;

use App\Models\CompanyBankAccount;
use App\Models\EmployeeBankAccount;
use App\Models\PaymentBatch;
use App\Models\SalarySheet;
use App\Models\SalarySheetLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class BankTransferTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_company_account_keeps_gst_optional_and_the_bulk_file_pays_each_net(): void
    {
        $org = $this->makeCompany('BNK');
        $accounts = $this->makeUser('accounts', $org['company']);
        $paid = $this->makeEmployee($org, 'BNK1');
        $unpaid = $this->makeEmployee($org, 'BNK2');
        EmployeeBankAccount::query()->create([
            'employee_id' => $paid->id,
            'beneficiary_name' => 'Ravi Solanki',
            'bank_name' => 'Example Bank',
            'account_number' => '111122223333',
            'account_last4' => '3333',
            'ifsc' => 'EXAM0001111',
            'branch_name' => 'Plant Road',
            'effective_from' => '2026-04-01',
            'is_active' => true,
        ]);

        $this->actingAsScoped($accounts)->post(route('bank.accounts.store'), [
            'label' => 'Salary',
            'account_holder' => 'Autotech',
            'bank_name' => 'Example Bank',
            'branch_name' => 'Main',
            'account_number' => '555566667777',
            'ifsc' => 'exam0002222',
            'is_primary' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAsScoped($accounts)->post(route('bank.accounts.store'), [
            'label' => 'Salary',
            'account_holder' => 'Autotech',
            'bank_name' => 'Example Bank',
            'account_number' => '555566667778',
            'ifsc' => 'EXAM0002222',
            'gst_number' => 'not-a-gst',
        ])->assertSessionHasErrors('gst_number');

        $this->actingAsScoped($accounts)->post(route('bank.accounts.store'), [
            'label' => 'Current',
            'account_holder' => 'Autotech Motors',
            'bank_name' => 'Example Bank',
            'branch_name' => 'Ring Road',
            'account_number' => '555566667779',
            'ifsc' => 'EXAM0003333',
            'gst_number' => '24abcde1234f1z5',
            'is_primary' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, CompanyBankAccount::query()->where('is_primary', true)->count());

        $page = $this->actingAsScoped($accounts)->get(route('bank.index'))->assertOk()->getContent();
        $this->assertStringContainsString('24ABCDE1234F1Z5', $page);
        $this->assertStringNotContainsString('555566667779', $page);

        $sheet = SalarySheet::query()->create([
            'company_id' => $org['company']->id,
            'title' => 'September',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'approved',
        ]);
        SalarySheetLine::query()->create([
            'salary_sheet_id' => $sheet->id,
            'employee_id' => $paid->id,
            'net' => 18000.50,
            'days' => [],
            'breakdown' => [],
        ]);
        SalarySheetLine::query()->create([
            'salary_sheet_id' => $sheet->id,
            'employee_id' => $unpaid->id,
            'net' => 9000,
            'days' => [],
            'breakdown' => [],
        ]);
        $accountId = CompanyBankAccount::query()->where('gst_number', '24ABCDE1234F1Z5')->value('id');

        $this->actingAsScoped($accounts)->post(route('bank.batches.store'), [
            'salary_sheet_id' => $sheet->id,
            'company_bank_account_id' => $accountId,
            'scheduled_on' => '2026-10-01',
        ])->assertRedirect()->assertSessionHas('success');

        $batchId = PaymentBatch::query()->value('id');
        $csv = $this->actingAsScoped($accounts)->get(route('bank.export', $batchId))->assertOk()->streamedContent();

        $this->assertStringContainsString('Employee code', $csv);
        $this->assertStringContainsString('Debit account', $csv);
        $this->assertStringContainsString('BNK1', $csv);
        $this->assertStringContainsString('18000.50', $csv);
        $this->assertStringContainsString('111122223333', $csv);
        $this->assertStringContainsString('555566667779', $csv);
        $this->assertStringContainsString('No active employee bank account', $csv);
        $this->assertStringContainsString('BNK2', $csv);
    }
}
