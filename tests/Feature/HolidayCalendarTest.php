<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class HolidayCalendarTest extends TestCase
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

    public function test_the_calendar_opens_on_the_current_month_and_uses_that_months_length(): void
    {
        Carbon::setTestNow('2026-11-19');
        $org = $this->makeCompany('HCL');
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeUser('employee', $org['company']);

        $this->actingAsScoped($hr)
            ->get(route('holidays.index'))
            ->assertOk()
            ->assertSee('November 2026', false)
            ->assertSee('data-days="30"', false)
            ->assertSee('One person’s paid day', false)
            ->assertSee('Public — paid day for everyone', false)
            ->assertSee('Cutoff — cut this day from everyone’s salary', false)
            ->assertDontSee('Restricted', false)
            ->assertDontSee('Optional', false);

        $this->actingAsScoped($employee)
            ->get(route('holidays.index', ['year' => 2026, 'month' => 11]))
            ->assertOk()
            ->assertSee('November 2026', false)
            ->assertDontSee('Add holiday', false);

        $this->actingAsScoped($hr)
            ->get(route('holidays.index', ['year' => 2026, 'month' => 4, 'partial' => 1]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('April 2026', false)
            ->assertSee('data-days="30"', false)
            ->assertDontSee('data-iso="2026-04-31"', false);

        $this->actingAsScoped($hr)
            ->get(route('holidays.index', ['year' => 2026, 'month' => 1]))
            ->assertSee('data-days="31"', false);

        $this->actingAsScoped($hr)
            ->get(route('holidays.index', ['year' => 2026, 'month' => 2]))
            ->assertSee('data-days="28"', false);

        $this->actingAsScoped($hr)
            ->get(route('holidays.index', ['year' => 2024, 'month' => 2]))
            ->assertSee('data-days="29"', false);
    }
}
