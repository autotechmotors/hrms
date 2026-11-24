<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\SalarySheet;
use App\Models\SalarySheetLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class SalaryNetAndAdvanceTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_net_pay_changes_one_person_with_a_required_reason(): void
    {
        $org = $this->makeCompany('NET');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $one = $this->makeEmployee($org, 'N1');
        $two = $this->makeEmployee($org, 'N2');
        $sheet = SalarySheet::query()->create([
            'company_id' => $org['company']->id,
            'title' => 'November',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-30',
            'status' => 'pending',
        ]);
        $line = SalarySheetLine::query()->create([
            'salary_sheet_id' => $sheet->id,
            'employee_id' => $one->id,
            'earned' => 20000,
            'advance' => 0,
            'deductions' => 0,
            'net' => 20500,
            'days' => [],
            'breakdown' => ['computed_net' => 20500, 'overtime_pay' => 500],
        ]);
        SalarySheetLine::query()->create([
            'salary_sheet_id' => $sheet->id,
            'employee_id' => $two->id,
            'earned' => 18000,
            'net' => 18000,
            'days' => [],
            'breakdown' => ['computed_net' => 18000],
        ]);

        $this->actingAsScoped($hr)
            ->postJson(route('salary-sheets.line.net', [$sheet, $line]), [
                'net' => 21000,
            ])
            ->assertStatus(422);

        $this->actingAsScoped($hr)
            ->postJson(route('salary-sheets.line.net', [$sheet, $line]), [
                'net' => 21000,
                'reason' => 'Festival top-up for this person',
            ])
            ->assertOk()
            ->assertJsonPath('net', 21000)
            ->assertJsonPath('computed_net', 20500)
            ->assertJsonPath('sheet_net', 39000);

        $line->refresh();
        $this->assertSame(21000.0, (float) $line->net);
        $this->assertSame('Festival top-up for this person', $line->breakdown['net_adjustment']['reason']);
        $this->assertSame(18000.0, (float) SalarySheetLine::query()->where('employee_id', $two->id)->value('net'));
    }

    public function test_a_pending_advance_can_be_edited_and_deleted(): void
    {
        $org = $this->makeCompany('ADV');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'A1');
        $advance = Advance::query()->create([
            'employee_id' => $employee->id,
            'title' => 'Cash',
            'amount' => 1000,
            'emi' => 1000,
            'balance' => 1000,
            'starts_on' => '2026-11-24',
            'status' => 'pending',
        ]);

        $this->actingAsScoped($hr)
            ->putJson(route('advances.update', $advance), [
                'title' => 'Travel',
                'amount' => 1500,
            ])
            ->assertOk();

        $advance->refresh();
        $this->assertSame('Travel', $advance->title);
        $this->assertSame(1500.0, (float) $advance->amount);
        $this->assertSame(1500.0, (float) $advance->balance);

        $this->actingAsScoped($hr)
            ->deleteJson(route('advances.destroy', $advance))
            ->assertOk();

        $this->assertDatabaseMissing('advances', ['id' => $advance->id]);
    }

    public function test_accountant_approves_or_rejects_an_advance_with_a_required_reason(): void
    {
        $org = $this->makeCompany('APR');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $accounts = $this->makeUser('accounts', $org['company']);
        $employee = $this->makeEmployee($org, 'P1');

        $this->actingAsScoped($hr)
            ->postJson(route('advances.store'), [
                'employee_id' => $employee->id,
                'title' => 'Festival',
                'amount' => 2500,
            ])
            ->assertOk();

        $advance = Advance::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('pending', $advance->status);
        $this->assertSame($hr->id, (int) $advance->created_by);

        $this->actingAsScoped($accounts)
            ->postJson(route('advances.reject', $advance), [])
            ->assertStatus(422);

        $this->actingAsScoped($accounts)
            ->postJson(route('advances.reject', $advance), [
                'reason' => 'No supporting note',
            ])
            ->assertOk();

        $advance->refresh();
        $this->assertSame('rejected', $advance->status);
        $this->assertSame('No supporting note', $advance->decision_note);
        $this->assertSame($accounts->id, (int) $advance->decided_by);

        $second = Advance::query()->create([
            'employee_id' => $employee->id,
            'created_by' => $hr->id,
            'title' => 'Travel',
            'amount' => 1000,
            'emi' => 1000,
            'balance' => 1000,
            'starts_on' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAsScoped($accounts)
            ->postJson(route('advances.approve', $second))
            ->assertOk();

        $second->refresh();
        $this->assertSame('active', $second->status);
        $this->assertSame($accounts->id, (int) $second->decided_by);
    }
}
