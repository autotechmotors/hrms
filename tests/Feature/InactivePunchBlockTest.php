<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\Role;
use App\Services\Employees\InactivePunchBlocker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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
                'out_inactive_minutes' => 10,
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

    public function test_two_full_empty_days_follow_the_saved_setting(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:15:00', 'Asia/Kolkata'));
        Cache::flush();

        $org = $this->makeCompany('ST4');
        CompanySetting::forCompany($org['company']->id)->update([
            'block_inactive_enabled' => true,
            'block_inactive_days' => 2,
        ]);

        $twoDays = $this->makeEmployee($org, 'OLD');
        $twoDays->update(['joining_date' => '2026-01-01']);
        AttendancePunch::query()->create([
            'employee_id' => $twoDays->id,
            'punched_at' => '2026-11-15 18:00:00',
            'direction' => 'out',
            'external_key' => 'st4-old',
        ]);

        $oneDay = $this->makeEmployee($org, 'MID');
        $oneDay->update(['joining_date' => '2026-01-01']);
        AttendancePunch::query()->create([
            'employee_id' => $oneDay->id,
            'punched_at' => '2026-11-16 09:00:00',
            'direction' => 'in',
            'external_key' => 'st4-mid',
        ]);

        $joined = $this->makeEmployee($org, 'JOIN');
        $joined->update(['joining_date' => '2026-11-16']);

        $joinedYesterday = $this->makeEmployee($org, 'NEW2');
        $joinedYesterday->update(['joining_date' => '2026-11-17']);

        $manual = $this->makeEmployee($org, 'MAN');
        $manual->update(['joining_date' => '2026-01-01']);
        AttendanceDay::query()->create([
            'employee_id' => $manual->id,
            'work_date' => '2026-11-17',
            'punch_in' => '09:00:00',
            'punch_out' => '18:00:00',
            'status' => 'present',
            'source' => 'manual',
        ]);

        $this->artisan('employees:block-inactive')->assertSuccessful();

        $this->assertTrue($twoDays->fresh()->is_blocked);
        $this->assertFalse($oneDay->fresh()->is_blocked);
        $this->assertTrue($joined->fresh()->is_blocked);
        $this->assertFalse($joinedYesterday->fresh()->is_blocked);
        $this->assertFalse($manual->fresh()->is_blocked);

        $this->artisan('employees:block-inactive')
            ->expectsOutputToContain('Already checked today.')
            ->assertSuccessful();

        Carbon::setTestNow();
    }

    public function test_command_blocks_nobody_when_the_rule_is_off(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 11:00:00', 'Asia/Kolkata'));
        Cache::flush();

        $org = $this->makeCompany('ST5');
        CompanySetting::forCompany($org['company']->id)->update([
            'block_inactive_enabled' => false,
            'block_inactive_days' => 2,
        ]);
        $worker = $this->makeEmployee($org, 'OFF1');
        $worker->update(['joining_date' => '2026-01-01']);

        $this->artisan('employees:block-inactive')->assertSuccessful();
        $this->assertFalse($worker->fresh()->is_blocked);

        Carbon::setTestNow();
    }
}
