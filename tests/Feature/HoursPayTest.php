<?php

namespace Tests\Feature;

use App\Models\DepartmentWorkHour;
use App\Models\EmployeeSalary;
use App\Models\EmployeeWorkHour;
use App\Services\Operations\WorkHourResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class HoursPayTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_department_hours_apply_until_a_person_sets_their_own(): void
    {
        $org = $this->makeCompany('HRS');
        $user = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'E1');

        $clocks = [
            'weekday_start' => '09:00',
            'weekday_end' => '17:00',
            'sunday_start' => '08:00',
            'sunday_end' => '13:00',
            'night_weekday_start' => '21:00',
            'night_weekday_end' => '06:00',
        ];

        $this->actingAsScoped($user)
            ->postJson(route('hours-pay.departments.store', $org['department']), $clocks)
            ->assertOk()
            ->assertJsonPath('value', '8');

        $this->assertDatabaseHas('department_work_hours', [
            'department_id' => $org['department']->id,
            'hours_per_day' => 8,
        ]);

        $this->actingAsScoped($user)
            ->get(route('hours-pay.show', $employee))
            ->assertOk()
            ->assertSee('Use department times');

        $this->actingAsScoped($user)
            ->postJson(route('hours-pay.employees.hours', $employee), $clocks + [
                'mode' => 'own',
                'shift_kind' => 'day',
                'weekday_end' => '16:00',
            ])
            ->assertOk();

        $this->assertTrue(
            EmployeeWorkHour::query()->where('employee_id', $employee->id)->where('uses_department', false)->exists()
        );
        $this->assertSame('8.00', DepartmentWorkHour::query()->where('department_id', $org['department']->id)->value('hours_per_day'));
    }

    public function test_monthly_salary_is_stored_for_payroll(): void
    {
        $org = $this->makeCompany('PAY');
        $user = $this->makeUser('hr-admin', $org['company']);
        $employee = $this->makeEmployee($org, 'E2');
        $this->actingAsScoped($user)
            ->postJson(route('hours-pay.employees.salary', $employee), [
                'gross_monthly' => 25000,
                'effective_from' => '2026-11-01',
            ])
            ->assertOk();

        $this->assertTrue(
            EmployeeSalary::query()->where('employee_id', $employee->id)->where('gross_monthly', 25000)->exists()
        );
    }

    public function test_manager_cannot_open_hours_and_pay(): void
    {
        $org = $this->makeCompany('MGR');
        $user = $this->makeUser('manager', $org['company']);

        $this->actingAsScoped($user)->get(route('hours-pay.index'))->assertForbidden();
    }

    public function test_sunday_night_is_a_department_switch_with_a_break(): void
    {
        $org = $this->makeCompany('SUN');
        $user = $this->makeUser('hr-admin', $org['company']);
        $night = $this->makeEmployee($org, 'N1');
        $day = $this->makeEmployee($org, 'D1');
        EmployeeWorkHour::query()->create([
            'employee_id' => $night->id,
            'uses_department' => true,
            'shift_kind' => 'night',
            'works_sunday' => true,
            'night_weekday_start' => '21:00:00',
            'night_weekday_end' => '06:00:00',
        ]);
        EmployeeWorkHour::query()->create([
            'employee_id' => $day->id,
            'uses_department' => true,
            'shift_kind' => 'day',
            'works_sunday' => false,
        ]);

        $hours = app(WorkHourResolver::class);
        $sunday = Carbon::parse('2026-11-15');
        $this->assertTrue($hours->worksSunday($night));
        $this->assertFalse($hours->worksSunday($day));

        $clocks = [
            'weekday_start' => '09:00',
            'weekday_end' => '18:00',
            'sunday_start' => '08:00',
            'sunday_end' => '13:00',
            'night_weekday_start' => '21:00',
            'night_weekday_end' => '06:00',
            'weekday_break_start' => '13:00',
            'weekday_break_end' => '14:00',
            'night_weekday_break_start' => '01:00',
            'night_weekday_break_end' => '02:00',
        ];

        $this->actingAsScoped($user)
            ->get(route('hours-pay.index'))
            ->assertOk()
            ->assertSee('Night · Sunday');

        $this->actingAsScoped($user)
            ->postJson(route('hours-pay.departments.store', $org['department']), $clocks + [
                'night_works_sunday' => '1',
                'night_sunday_start' => '21:00',
                'night_sunday_end' => '02:00',
            ])
            ->assertStatus(422);

        $this->actingAsScoped($user)
            ->postJson(route('hours-pay.departments.store', $org['department']), $clocks + [
                'night_works_sunday' => '1',
                'night_sunday_start' => '21:00',
                'night_sunday_end' => '02:00',
                'night_sunday_break_start' => '01:00',
                'night_sunday_break_end' => '02:00',
            ])
            ->assertOk();

        $night->unsetRelation('workHour');
        $night->unsetRelation('department');
        $day->unsetRelation('workHour');
        $day->unsetRelation('department');

        $this->assertTrue($hours->worksSunday($night->fresh()));
        $this->assertFalse($hours->worksSunday($day->fresh()));

        $window = $hours->windowFor($night->fresh(), $sunday);
        $this->assertSame('21:00', $window['start']);
        $this->assertSame('02:00', $window['end']);
        $this->assertSame(
            ['start' => '01:00', 'end' => '02:00'],
            $hours->breakClocks($night->fresh(), $sunday)
        );

        $this->assertDatabaseHas('department_work_hours', [
            'department_id' => $org['department']->id,
            'night_works_sunday' => true,
        ]);

        $night->workHour->update([
            'uses_department' => false,
            'works_sunday' => true,
            'night_sunday_start' => '22:00:00',
            'night_sunday_end' => '03:00:00',
            'night_sunday_break_start' => '01:00:00',
            'night_sunday_break_end' => '02:00:00',
        ]);
        $own = $night->fresh();
        $ownWindow = $hours->windowFor($own, $sunday);
        $this->assertSame('22:00', $ownWindow['start']);
        $this->assertSame('03:00', $ownWindow['end']);
        $this->assertSame(['start' => '01:00', 'end' => '02:00'], $hours->breakClocks($own, $sunday));
    }
}
