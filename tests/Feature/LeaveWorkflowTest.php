<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmployeeSalary;
use App\Models\GatePass;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SalaryHead;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureLine;
use App\Services\Operations\PayDayMath;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class LeaveWorkflowTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    public function test_department_leave_is_approved_by_the_parent_and_cuts_only_when_marked(): void
    {
        $this->seedAccess();
        $org = $this->makeCompany('LVD');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $parentUser = $this->makeUser('manager', $org['company']);
        $employeeUser = $this->makeUser('employee', $org['company']);
        $parent = $this->makeEmployee($org, 'PAR');
        $parent->update(['user_id' => $parentUser->id]);
        $employee = $this->makeEmployee($org, 'EMP', $parent->id);
        $employee->update(['user_id' => $employeeUser->id]);

        $this->actingAsScoped($hr)->postJson(route('leave.types.store'), [
            'department_id' => $org['department']->id,
            'name' => 'Casual',
            'code' => 'CL',
            'cuts_pay' => '1',
        ])->assertOk();

        $type = LeaveType::query()->first();
        $this->assertFalse((bool) $type->is_paid);
        $this->assertSame($org['department']->id, $type->department_id);

        $this->actingAsScoped($hr)->putJson(route('leave.types.update', $type), [
            'department_id' => $org['department']->id,
            'name' => 'Casual leave',
            'code' => 'CL',
            'cuts_pay' => '1',
        ])->assertOk();
        $this->assertSame('Casual leave', $type->fresh()->name);
        $this->actingAsScoped($hr)->get(route('leave.index'))->assertOk()->assertSee('Casual leave')->assertSee('Salary cutoff');
        $this->actingAsScoped($employeeUser)->get(route('leave.index'))->assertOk()->assertSee('Request leave');

        $this->actingAsScoped($employeeUser)->postJson(route('leave.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'starts_on' => '2026-11-18',
            'ends_on' => '2026-11-18',
            'reason' => 'Family function',
        ])->assertOk();

        $leave = LeaveRequest::query()->first();
        $this->assertSame('pending', $leave->status);
        $this->assertSame(1, $parentUser->notifications()->count());

        $this->actingAsScoped($employeeUser)->postJson(route('leave.decide', $leave), [
            'decision' => 'approved',
        ])->assertStatus(422);

        $this->actingAsScoped($parentUser)->postJson(route('leave.decide', $leave), [
            'decision' => 'rejected',
        ])->assertStatus(422);

        $this->actingAsScoped($parentUser)->postJson(route('leave.decide', $leave), [
            'decision' => 'rejected',
            'decision_note' => 'Peak production day',
        ])->assertOk();
        $this->assertSame('Peak production day', $leave->fresh()->decision_note);

        $this->actingAsScoped($employeeUser)->postJson(route('leave.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'starts_on' => '2026-11-18',
            'ends_on' => '2026-11-18',
            'reason' => 'Family function',
        ])->assertOk();

        $again = LeaveRequest::query()->where('status', 'pending')->first();
        $this->actingAsScoped($hr)->postJson(route('leave.decide', $again), [
            'decision' => 'approved',
        ])->assertOk();

        Holiday::query()->create([
            'company_id' => $org['company']->id,
            'holiday_date' => '2026-11-19',
            'name' => 'Diwali',
            'kind' => 'public',
        ]);
        GatePass::query()->create([
            'company_id' => $org['company']->id,
            'number' => 'GP-LVD-1',
            'employee_id' => $employee->id,
            'date' => '2026-11-18',
            'out_time' => '11:00:00',
            'duration_minutes' => 90,
            'work_type' => 'office',
            'reason' => 'Client visit',
            'status' => 'approved',
        ]);

        $head = SalaryHead::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Basic',
            'code' => 'BASIC',
            'category' => 'earning',
            'method' => 'fixed',
            'value' => 0,
            'is_active' => true,
        ]);
        $structure = SalaryStructure::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Monthly',
            'code' => 'MON',
            'is_active' => true,
        ]);
        SalaryStructureLine::query()->create([
            'salary_structure_id' => $structure->id,
            'salary_head_id' => $head->id,
            'value' => 0,
        ]);
        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'gross_monthly' => 30000,
            'effective_from' => '2026-01-01',
        ]);

        $pay = app(PayDayMath::class)->range($employee, Carbon::parse('2026-11-18'), Carbon::parse('2026-11-19'));
        $this->assertSame('UL', $pay['days']['2026-11-18']['status']);
        $this->assertSame('Leave · Casual leave · cutoff', $pay['days']['2026-11-18']['leave']);
        $this->assertStringContainsString('Office', $pay['days']['2026-11-18']['gate']);
        $this->assertGreaterThan(0, $pay['cutoff']);
        $this->assertSame('H', $pay['days']['2026-11-19']['status']);
        $this->assertSame('Company holiday · Diwali', $pay['days']['2026-11-19']['holiday']);
        $this->assertSame('paid holiday', $pay['cuts'][1]['reason'] ?? $pay['cuts'][0]['reason']);

        $spare = LeaveType::query()->create([
            'company_id' => $org['company']->id,
            'department_id' => $org['department']->id,
            'name' => 'Unused',
            'code' => 'UN',
            'is_paid' => true,
            'annual_days' => 0,
            'is_active' => true,
        ]);
        $this->actingAsScoped($hr)->deleteJson(route('leave.types.destroy', $spare))->assertOk();
        $this->actingAsScoped($hr)->deleteJson(route('leave.types.destroy', $type))->assertOk();
        $this->assertFalse($type->fresh()->is_active);
        $this->actingAsScoped($hr)->get(route('leave.index'))
            ->assertOk()
            ->assertDontSee('CL — Casual leave', false);
        $this->actingAsScoped($hr)->postJson(route('leave.types.store'), [
            'department_id' => $org['department']->id,
            'name' => 'Casual leave',
            'code' => 'CL',
            'cuts_pay' => '1',
        ])->assertOk();

        $other = Department::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Store',
            'code' => 'STR',
            'is_active' => true,
        ]);
        $this->actingAsScoped($hr)->postJson(route('leave.types.store'), [
            'department_ids' => [$org['department']->id, $other->id],
            'name' => 'Sick',
            'code' => 'SL',
            'cuts_pay' => '0',
        ])->assertOk();
        $this->assertSame(2, LeaveType::query()->where('code', 'SL')->where('is_active', true)->count());

        $open = LeaveType::query()->create([
            'company_id' => $org['company']->id,
            'department_id' => null,
            'name' => 'Paid leave',
            'code' => 'SGPL',
            'is_paid' => true,
            'annual_days' => 0,
            'is_active' => true,
        ]);
        $this->actingAsScoped($hr)->putJson(route('leave.types.update', $open), [
            'department_id' => '',
            'name' => 'Paid leave',
            'code' => 'SGPL',
            'cuts_pay' => '0',
        ])->assertOk();
        $this->assertNull($open->fresh()->department_id);
    }

    public function test_a_department_leave_type_is_only_for_that_department(): void
    {
        $this->seedAccess();
        $org = $this->makeCompany('LVD2');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $outsider = $this->makeEmployee($org, 'OUT');
        $accounts = Department::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Accounts',
            'code' => 'ACC',
            'is_active' => true,
        ]);
        $this->actingAsScoped($hr)->postJson(route('leave.types.store'), [
            'department_id' => $accounts->id,
            'name' => 'Accounts casual',
            'code' => 'ACL',
            'cuts_pay' => '0',
        ])->assertOk();
        $type = LeaveType::query()->where('code', 'ACL')->first();

        $this->actingAsScoped($hr)->postJson(route('leave.store'), [
            'employee_id' => $outsider->id,
            'leave_type_id' => $type->id,
            'starts_on' => '2026-11-20',
            'ends_on' => '2026-11-20',
            'reason' => 'Errand',
        ])->assertStatus(422);

        $insider = $this->makeEmployee($org, 'ACC');
        $insider->update(['department_id' => $accounts->id]);
        $this->actingAsScoped($hr)->postJson(route('leave.store'), [
            'employee_id' => $insider->id,
            'leave_type_id' => $type->id,
            'starts_on' => '2026-11-20',
            'ends_on' => '2026-11-20',
            'reason' => 'Errand',
        ])->assertOk();
    }
}
