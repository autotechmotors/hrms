<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\SalaryHead;
use App\Models\SalaryStructure;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class OperationsModulesTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_hr_can_record_a_shift_holiday_punch_and_payroll(): void
    {
        $org = $this->makeCompany('OPS');
        $employee = $this->makeEmployee($org, 'E9');
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)
            ->post(route('shifts.store'), [
                'name' => 'General',
                'code' => 'GEN',
                'starts_at' => '09:00',
                'ends_at' => '18:00',
                'break_minutes' => 60,
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue(Shift::query()->where('code', 'GEN')->where('company_id', $org['company']->id)->exists());

        $this->post(route('holidays.store'), [
            'holiday_date' => '2026-10-02',
            'name' => 'Gandhi Jayanti',
            'kind' => 'public',
        ])->assertRedirect();
        $this->assertTrue(Holiday::query()->where('name', 'Gandhi Jayanti')->exists());

        $this->post(route('attendance.punch'), [
            'employee_id' => $employee->id,
            'work_date' => '2026-10-16',
            'punch_in' => '09:05',
            'punch_out' => '18:00',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('present', AttendanceDay::query()->where('employee_id', $employee->id)->value('status'));

        $this->post(route('salary.heads.store'), [
            'name' => 'Basic',
            'code' => 'BASIC',
            'category' => 'earning',
            'method' => 'percent_gross',
            'value' => 100,
        ])->assertRedirect();

        $this->post(route('salary.structures.store'), [
            'name' => 'Standard',
            'code' => 'STD',
            'head_ids' => [SalaryHead::query()->value('id')],
        ])->assertRedirect();

        $this->post(route('salary.assign'), [
            'employee_id' => $employee->id,
            'salary_structure_id' => SalaryStructure::query()->value('id'),
            'gross_monthly' => 30000,
            'effective_from' => '2026-04-01',
        ])->assertRedirect();

        $this->post(route('payroll.store'), [
            'period_year' => 2026,
            'period_month' => 10,
        ])->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->post(route('payroll.compute', $run))->assertRedirect()->assertSessionHas('success');
        $run->refresh();
        $this->assertSame('computed', $run->status);
        $this->assertTrue($run->items()->where('employee_id', $employee->id)->where('net', '>', 0)->exists());

        foreach (['attendance.index', 'shifts.index', 'leave.index', 'holidays.index', 'salary.index', 'payroll.index', 'advances.index', 'devices.index', 'reports.index', 'bank.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_manager_cannot_change_shifts(): void
    {
        $org = $this->makeCompany('OPS');
        $manager = $this->makeUser('manager', $org['company']);

        $this->actingAsScoped($manager)->get(route('shifts.index'))->assertOk();
        $this->post(route('shifts.store'), [
            'name' => 'General',
            'code' => 'GEN',
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ])->assertForbidden();
    }
}
