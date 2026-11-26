<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeDocument;
use App\Models\EmployeeSalary;
use App\Models\EmploymentEvent;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class EmployeeDeleteCascadeTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        Storage::fake('local');
    }

    public function test_deleting_an_employee_removes_related_system_rows_and_login(): void
    {
        $org = $this->makeCompany('DEL');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'DEL1');
        $login = $this->makeUser('manager', $org['company']);
        $employee->update([
            'user_id' => $login->id,
            'role_id' => $login->roles()->value('id'),
            'email' => $login->email,
        ]);

        Storage::disk('local')->put('employee-docs/'.$employee->id.'/note.txt', 'paper');
        EmployeeDocument::query()->create([
            'employee_id' => $employee->id,
            'title' => 'ID proof',
            'file_path' => 'employee-docs/'.$employee->id.'/note.txt',
            'original_name' => 'note.txt',
            'mime' => 'text/plain',
            'size' => 5,
        ]);
        EmployeeBankAccount::query()->create([
            'employee_id' => $employee->id,
            'beneficiary_name' => 'Test DEL1',
            'bank_name' => 'Demo Bank',
            'account_number' => '1234567890',
            'account_last4' => '7890',
            'ifsc' => 'DEMO0001234',
            'effective_from' => '2026-04-01',
            'is_active' => true,
        ]);
        EmploymentEvent::query()->create([
            'employee_id' => $employee->id,
            'event_type' => 'status_change',
            'effective_date' => '2026-05-01',
            'reason' => 'Confirmed',
            'from_department_id' => $org['department']->id,
            'to_department_id' => $org['department']->id,
            'from_designation_id' => $org['designation']->id,
            'to_designation_id' => $org['designation']->id,
        ]);
        AttendanceDay::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-11-03',
            'status' => 'present',
            'worked_minutes' => 540,
        ]);
        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'gross_monthly' => 20000,
            'effective_from' => '2026-04-01',
        ]);
        Advance::query()->create([
            'employee_id' => $employee->id,
            'title' => 'Festival',
            'amount' => 1000,
            'emi' => 1000,
            'balance' => 1000,
            'starts_on' => '2026-11-01',
            'status' => 'pending',
        ]);
        $type = LeaveType::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Casual',
            'code' => 'CL',
            'is_paid' => false,
            'annual_days' => 0,
            'is_active' => true,
        ]);
        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-10',
            'days' => 1,
            'reason' => 'Personal',
            'status' => 'pending',
        ]);

        $this->actingAsScoped($hr)
            ->deleteJson(route('employees.destroy', $employee))
            ->assertOk();

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
        $this->assertDatabaseMissing('employee_documents', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('employee_bank_accounts', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('employment_events', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('attendance_days', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('employee_salaries', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('advances', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('users', ['id' => $login->id]);
        Storage::disk('local')->assertMissing('employee-docs/'.$employee->id.'/note.txt');
    }

    public function test_deleting_a_manager_clears_parent_on_reports(): void
    {
        $org = $this->makeCompany('MGR');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $manager = $this->makeEmployee($org, 'MGR1');
        $report = $this->makeEmployee($org, 'REP1', $manager->id);

        $this->actingAsScoped($hr)
            ->deleteJson(route('employees.destroy', $manager))
            ->assertOk();

        $this->assertDatabaseMissing('employees', ['id' => $manager->id]);
        $this->assertNull(Employee::query()->find($report->id)?->reporting_manager_id);
    }
}
