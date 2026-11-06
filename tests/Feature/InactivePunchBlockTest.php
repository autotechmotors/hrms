<?php

namespace Tests\Feature;

use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\Role;
use App\Services\Employees\InactivePunchBlocker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class InactivePunchBlockTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_only_super_admin_and_hr_admin_can_open_settings(): void
    {
        $org = $this->makeCompany('ST1');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $admin = $this->makeUser('super-admin', $org['company']);
        $manager = $this->makeUser('manager', $org['company']);

        $this->actingAsScoped($hr)->get(route('settings.edit'))->assertOk()->assertSee('Auto-block without in / out');
        $this->actingAsScoped($admin)->get(route('settings.edit'))->assertOk();
        $this->actingAsScoped($manager)->get(route('settings.edit'))->assertForbidden();

        $this->actingAsScoped($manager)
            ->get(route('more'))
            ->assertOk()
            ->assertDontSee('Settings', false);
    }

    public function test_idle_employee_is_blocked_but_admin_is_not(): void
    {
        $org = $this->makeCompany('ST2');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $worker = $this->makeEmployee($org, 'WK1');
        $worker->update([
            'role_id' => Role::query()->where('slug', 'manager')->value('id'),
            'joining_date' => now()->subDays(20)->toDateString(),
        ]);
        $protected = $this->makeEmployee($org, 'ADM1');
        $protected->update([
            'role_id' => Role::query()->where('slug', 'hr-admin')->value('id'),
            'user_id' => $hr->id,
            'joining_date' => now()->subDays(20)->toDateString(),
        ]);
        $fresh = $this->makeEmployee($org, 'NEW1');
        $fresh->update(['joining_date' => now()->toDateString()]);

        AttendancePunch::query()->create([
            'employee_id' => $fresh->id,
            'punched_at' => now()->subHours(2),
            'direction' => 'in',
            'external_key' => 'st2-fresh',
        ]);

        $this->actingAsScoped($hr)
            ->put(route('settings.update'), [
                'block_inactive_enabled' => '1',
                'block_inactive_days' => 3,
            ])
            ->assertRedirect(route('settings.edit'));

        $this->assertTrue($worker->fresh()->is_blocked);
        $this->assertFalse($protected->fresh()->is_blocked);
        $this->assertFalse($fresh->fresh()->is_blocked);
    }

    public function test_recent_in_out_keeps_employee_active(): void
    {
        $org = $this->makeCompany('ST3');
        $employee = $this->makeEmployee($org, 'WK2');
        $employee->update(['joining_date' => now()->subDays(30)->toDateString()]);
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'punched_at' => now()->subDay(),
            'direction' => 'out',
            'external_key' => 'st3-out',
        ]);

        CompanySetting::forCompany($org['company']->id)->update([
            'block_inactive_enabled' => true,
            'block_inactive_days' => 3,
        ]);

        $this->assertSame(0, app(InactivePunchBlocker::class)->apply($org['company']->id));
        $this->assertFalse($employee->fresh()->is_blocked);
    }
}
