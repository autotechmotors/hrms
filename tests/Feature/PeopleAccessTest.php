<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class PeopleAccessTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    public function test_a_parent_sees_and_decides_the_people_under_them(): void
    {
        $this->seedAccess();
        $org = $this->makeCompany('PAC');
        $parent = $this->makeEmployee($org, 'PAR');
        $child = $this->makeEmployee($org, 'CHD', $parent->id);
        $other = $this->makeEmployee($org, 'OUT');
        $parentUser = $this->makeUser('employee', $org['company']);
        $childUser = $this->makeUser('employee', $org['company']);
        $parent->update(['user_id' => $parentUser->id]);
        $child->update(['user_id' => $childUser->id]);
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($parentUser)->get(route('employees.index'))
            ->assertOk()
            ->assertSee('CHD')
            ->assertDontSee('OUT')
            ->assertDontSee('aria-label="Salary"', false);
        $this->actingAsScoped($parentUser)->get(route('employees.edit', $child))->assertForbidden();
        $this->actingAsScoped($hr)->get(route('employees.edit', $child))->assertOk();
        $this->actingAsScoped($hr)->get(route('employees.index'))
            ->assertOk()
            ->assertSee('OUT')
            ->assertSee('aria-label="Salary"', false)
            ->assertSee(route('hours-pay.show', $child), false);

        $type = LeaveType::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Casual',
            'code' => 'CL',
            'is_paid' => false,
            'annual_days' => 0,
            'is_active' => true,
        ]);
        $this->actingAsScoped($childUser)->postJson(route('leave.store'), [
            'employee_id' => $child->id,
            'leave_type_id' => $type->id,
            'starts_on' => '2026-11-20',
            'ends_on' => '2026-11-20',
            'reason' => 'Family function',
        ])->assertOk();

        $leave = LeaveRequest::query()->first();
        $this->actingAsScoped($parentUser)->postJson(route('leave.decide', $leave), [
            'decision' => 'rejected',
            'decision_note' => 'Busy week',
        ])->assertOk();
        $this->actingAsScoped($parentUser)->deleteJson(route('leave.destroy', $leave))->assertForbidden();

        $this->actingAsScoped($parentUser)->postJson(route('leave.store'), [
            'employee_id' => $parent->id,
            'leave_type_id' => $type->id,
            'starts_on' => '2026-11-21',
            'ends_on' => '2026-11-21',
            'reason' => 'Own leave',
        ])->assertOk();
        $own = LeaveRequest::query()->where('employee_id', $parent->id)->first();
        $this->actingAsScoped($parentUser)->postJson(route('leave.decide', $own), [
            'decision' => 'approved',
        ])->assertStatus(422);

        $this->actingAsScoped($childUser)->get(route('holidays.index'))
            ->assertOk()
            ->assertDontSee('Add holiday');
        $this->actingAsScoped($hr)->get(route('holidays.index'))
            ->assertOk()
            ->assertSee('Add holiday');
    }
}
