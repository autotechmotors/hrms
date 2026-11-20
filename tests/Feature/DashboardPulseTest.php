<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\EmployeeWorkHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class DashboardPulseTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    public function test_home_shows_today_and_the_week_for_people_in_scope(): void
    {
        $this->seedAccess();
        $org = $this->makeCompany('HOME');
        $person = $this->makeEmployee($org, 'ONE');
        $person->update(['is_blocked' => true]);
        EmployeeWorkHour::query()->create([
            'employee_id' => $person->id,
            'uses_department' => false,
            'shift_kind' => 'night',
            'hours_per_day' => 9,
        ]);
        AttendanceDay::query()->create([
            'employee_id' => $person->id,
            'work_date' => today()->toDateString(),
            'status' => 'present',
            'source' => 'device',
        ]);

        $this->actingAsScoped($this->makeUser('hr-admin', $org['company']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Today')
            ->assertSee('Last 7 days')
            ->assertSee('Seven-day numbers')
            ->assertSee('Present today')
            ->assertSee('Night');
    }
}
