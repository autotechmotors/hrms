<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Models\DepartmentWorkHour;
use App\Models\EmployeeHoliday;
use App\Models\EmployeeSalary;
use App\Models\EmployeeWorkHour;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\SalaryHead;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureLine;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Models\WorkCalendar;
use App\Models\WorkCalendarDay;
use App\Services\Operations\AttendanceCalculator;
use App\Services\Operations\PayDayMath;
use App\Services\Operations\WorkCalendarResolver;
use App\Services\Operations\WorkWindowDefaults;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class PayDayTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_every_weekday_works_until_a_public_holiday_is_added(): void
    {
        $org = $this->makeCompany('PAY');
        $resolver = app(WorkCalendarResolver::class);
        $companyId = (int) $org['company']->id;

        $this->assertTrue($resolver->isWorkingDay($companyId, Carbon::parse('2026-11-03')));
        $this->assertTrue($resolver->isWorkingDay($companyId, Carbon::parse('2026-11-01')));

        Holiday::query()->create([
            'company_id' => $companyId,
            'holiday_date' => '2026-11-01',
            'name' => 'Founders day',
            'kind' => 'public',
        ]);

        $this->assertFalse($resolver->isWorkingDay($companyId, Carbon::parse('2026-11-01')));
        $this->assertTrue($resolver->isWorkingDay($companyId, Carbon::parse('2026-11-03')));
    }

    public function test_active_calendar_sundays_are_working_and_defaults_do_not_touch_salary(): void
    {
        $org = $this->makeCompany('CAL');
        $calendar = WorkCalendar::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Week',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
        WorkCalendarDay::query()->create([
            'work_calendar_id' => $calendar->id,
            'weekday' => 0,
            'is_working' => false,
        ]);

        $employee = $this->makeEmployee($org, 'OWN1');
        $structure = $this->structure($org);
        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'gross_monthly' => 25000,
            'effective_from' => '2026-04-01',
        ]);
        EmployeeWorkHour::query()->create([
            'employee_id' => $employee->id,
            'uses_department' => false,
            'hours_per_day' => 8,
        ]);

        WorkWindowDefaults::apply();

        $this->assertTrue((bool) $calendar->days()->where('weekday', 0)->value('is_working'));
        $this->assertSame('09:00:00', substr((string) $org['department']->workHour()->value('weekday_start'), 0, 8));
        $this->assertSame('13:00:00', substr((string) $org['department']->workHour()->value('sunday_end'), 0, 8));
        $hour = $employee->workHour()->first();
        $this->assertFalse($hour->uses_department);
        $this->assertSame('08:00:00', substr((string) $hour->sunday_start, 0, 8));
        $this->assertSame('25000.00', $employee->salaries()->value('gross_monthly'));
    }

    public function test_full_months_pay_the_monthly_salary_and_sunday_uses_the_540_minute_rate(): void
    {
        $org = $this->makeCompany('CUT');
        $employee = $this->makeEmployee($org, 'A1');
        $other = $this->makeEmployee($org, 'A2');
        $this->paySalary($employee, $this->structure($org), 25000);
        $this->paySalary($other, SalaryStructure::query()->first(), 25000);
        AttendanceRule::query()->create([
            'company_id' => $org['company']->id,
            'grace_minutes' => 0,
            'mark_absent_without_punch' => true,
        ]);

        $this->fillMonth($employee, 2026, 4);
        $this->fillMonth($employee, 2026, 5);
        $this->fillMonth($employee, 2028, 2);

        $math = app(PayDayMath::class);
        $april = $math->range($employee, Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));
        $may = $math->range($employee, Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'));
        $february = $math->range($employee, Carbon::parse('2028-02-01'), Carbon::parse('2028-02-29'));

        $this->assertCount(30, $april['days']);
        $this->assertCount(31, $may['days']);
        $this->assertCount(29, $february['days']);
        $this->assertEqualsWithDelta(25000, $april['earned'], 0.02);
        $this->assertEqualsWithDelta(25000, $may['earned'], 0.02);
        $this->assertEqualsWithDelta(25000, $february['earned'], 0.02);
        AttendanceDay::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => '2026-11-01'],
            ['punch_in' => '08:30:00', 'punch_out' => '13:00:00', 'status' => 'present', 'source' => 'manual']
        );
        $late = $math->range($employee, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-01'));
        $this->assertCount(1, $late['cuts']);
        $this->assertSame(30, $late['cuts'][0]['minutes']);
        $this->assertSame('late', $late['cuts'][0]['reason']);
        $this->assertEqualsWithDelta(round((25000 / 30) * 30 / 540, 2), $late['cuts'][0]['rupees'], 0.02);

        AttendanceDay::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => '2026-11-08'],
            ['punch_in' => '08:00:00', 'punch_out' => '12:00:00', 'status' => 'present', 'source' => 'manual']
        );
        $early = $math->range($employee, Carbon::parse('2026-11-08'), Carbon::parse('2026-11-08'));
        $this->assertSame(60, $early['cuts'][0]['minutes']);
        $this->assertSame('left early', $early['cuts'][0]['reason']);

        AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-11-15')->delete();
        $absent = $math->range($employee, Carbon::parse('2026-11-15'), Carbon::parse('2026-11-15'));
        $this->assertSame('absent', $absent['cuts'][0]['reason']);
        $this->assertEqualsWithDelta(25000 / 30, $absent['cuts'][0]['rupees'], 0.02);
        $this->assertSame(0.0, $absent['earned']);
    }

    public function test_a_cutoff_holiday_cuts_everyones_pay_and_a_personal_range_pays_one_person(): void
    {
        $org = $this->makeCompany('CUT');
        $one = $this->makeEmployee($org, 'C1');
        $two = $this->makeEmployee($org, 'C2');
        $structure = $this->structure($org);
        $this->paySalary($one, $structure, 31000);
        $this->paySalary($two, $structure, 31000);
        $hr = $this->makeUser('hr-admin', $org['company']);

        $this->actingAsScoped($hr)->postJson(route('holidays.store'), [
            'holiday_date' => '2026-05-31',
            'name' => 'Year end',
            'kind' => 'restricted',
        ])->assertStatus(422);

        $this->actingAsScoped($hr)->postJson(route('holidays.store'), [
            'holiday_date' => '2026-05-31',
            'name' => 'Year end',
            'kind' => 'cutoff',
        ])->assertOk();

        $math = app(PayDayMath::class);
        $cut = $math->range($one, Carbon::parse('2026-05-31'), Carbon::parse('2026-05-31'));
        $this->assertSame('holiday cutoff', $cut['cuts'][0]['reason']);
        $this->assertEqualsWithDelta(31000 / 31, $cut['cutoff'], 0.02);
        $this->assertSame('Company holiday · Year end · cutoff', $cut['days']['2026-05-31']['holiday']);
        $also = $math->range($two, Carbon::parse('2026-05-31'), Carbon::parse('2026-05-31'));
        $this->assertSame('holiday cutoff', $also['cuts'][0]['reason']);
        $this->assertSame('holiday', AttendanceDay::query()->where('employee_id', $one->id)->whereDate('work_date', '2026-05-31')->value('status'));

        $this->actingAsScoped($hr)->postJson(route('employee-holidays.store'), [
            'employee_id' => $one->id,
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-03',
            'name' => 'Family',
        ])->assertOk();

        $this->assertSame(3, EmployeeHoliday::query()->where('employee_id', $one->id)->count());
        $paid = $math->range($one, Carbon::parse('2026-06-02'), Carbon::parse('2026-06-02'));
        $stillWorks = $math->range($two, Carbon::parse('2026-06-02'), Carbon::parse('2026-06-02'));
        $this->assertSame('paid holiday', $paid['cuts'][0]['reason']);
        $this->assertSame('absent', $stillWorks['cuts'][0]['reason']);
    }

    public function test_a_company_holiday_pays_everyone_and_a_person_holiday_pays_only_that_person(): void
    {
        $org = $this->makeCompany('HOL');
        $one = $this->makeEmployee($org, 'H1');
        $two = $this->makeEmployee($org, 'H2');
        $structure = $this->structure($org);
        $this->paySalary($one, $structure, 25000);
        $this->paySalary($two, $structure, 25000);
        $hr = $this->makeUser('hr-admin', $org['company']);
        $employeeLogin = $this->makeUser('employee', $org['company']);

        $this->actingAsScoped($employeeLogin)
            ->post(route('holidays.store'), [
                'holiday_date' => '2026-11-01',
                'name' => 'Closed',
                'kind' => 'public',
            ])
            ->assertForbidden();

        $this->actingAsScoped($hr)
            ->postJson(route('holidays.store'), [
                'holiday_date' => '2026-11-01',
                'name' => 'Closed',
                'kind' => 'public',
            ])
            ->assertOk();

        $math = app(PayDayMath::class);
        $paidOne = $math->range($one, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-01'));
        $paidTwo = $math->range($two, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-01'));
        $this->assertSame('paid holiday', $paidOne['cuts'][0]['reason']);
        $this->assertSame(0.0, $paidOne['cuts'][0]['rupees']);
        $this->assertSame('paid holiday', $paidTwo['cuts'][0]['reason']);
        $this->assertSame('holiday', AttendanceDay::query()->where('employee_id', $one->id)->whereDate('work_date', '2026-11-01')->value('status'));

        $this->actingAsScoped($hr)
            ->postJson(route('employee-holidays.store'), [
                'employee_id' => $one->id,
                'holiday_date' => '2026-11-08',
                'name' => 'Sunday off',
            ])
            ->assertOk();

        $this->assertSame(0, LeaveRequest::query()->count());
        $only = $math->range($one, Carbon::parse('2026-11-08'), Carbon::parse('2026-11-08'));
        $stillWorks = $math->range($two, Carbon::parse('2026-11-08'), Carbon::parse('2026-11-08'));
        $this->assertSame('paid holiday', $only['cuts'][0]['reason']);
        $this->assertSame('absent', $stillWorks['cuts'][0]['reason']);
        $this->assertSame('08:00–13:00', $stillWorks['cuts'][0]['window']);

        $row = EmployeeHoliday::query()->first();
        $this->actingAsScoped($hr)
            ->deleteJson(route('employee-holidays.destroy', $row))
            ->assertOk();
        $back = $math->range($one, Carbon::parse('2026-11-08'), Carbon::parse('2026-11-08'));
        $this->assertSame('absent', $back['cuts'][0]['reason']);
    }

    public function test_night_shift_minutes_follow_that_employees_clock(): void
    {
        $org = $this->makeCompany('NIGHT');
        $dayPerson = $this->makeEmployee($org, 'D1');
        $nightPerson = $this->makeEmployee($org, 'N1');
        $structure = $this->structure($org);
        $this->paySalary($dayPerson, $structure, 25000);
        $this->paySalary($nightPerson, $structure, 25000);
        DepartmentWorkHour::query()->create([
            'department_id' => $org['department']->id,
            'hours_per_day' => 9,
            'weekday_start' => '09:00:00',
            'weekday_end' => '18:00:00',
            'sunday_start' => '08:00:00',
            'sunday_end' => '13:00:00',
            'night_weekday_start' => '21:00:00',
            'night_weekday_end' => '06:00:00',
            'night_sunday_start' => '21:00:00',
            'night_sunday_end' => '02:00:00',
        ]);
        EmployeeWorkHour::query()->create([
            'employee_id' => $nightPerson->id,
            'uses_department' => true,
            'shift_kind' => 'night',
        ]);

        $math = app(PayDayMath::class);
        AttendanceDay::query()->create([
            'employee_id' => $nightPerson->id,
            'work_date' => '2026-11-02',
            'punch_in' => '21:30:00',
            'punch_out' => '06:00:00',
            'status' => 'present',
            'source' => 'manual',
        ]);
        $late = $math->range($nightPerson, Carbon::parse('2026-11-02'), Carbon::parse('2026-11-02'));
        $this->assertSame('21:00–06:00', $late['cuts'][0]['window']);
        $this->assertSame(30, $late['cuts'][0]['minutes']);
        $this->assertSame('late', $late['cuts'][0]['reason']);
        $this->assertEqualsWithDelta(round((25000 / 30) * 30 / 540, 2), $late['cuts'][0]['rupees'], 0.02);

        AttendanceDay::query()->create([
            'employee_id' => $nightPerson->id,
            'work_date' => '2026-11-03',
            'punch_in' => '21:00:00',
            'punch_out' => '05:00:00',
            'status' => 'present',
            'source' => 'manual',
        ]);
        $early = $math->range($nightPerson, Carbon::parse('2026-11-03'), Carbon::parse('2026-11-03'));
        $this->assertSame(60, $early['cuts'][0]['minutes']);
        $this->assertSame('left early', $early['cuts'][0]['reason']);

        $night = Shift::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Night',
            'code' => 'NITE',
            'starts_at' => '22:00:00',
            'ends_at' => '07:00:00',
            'break_minutes' => 0,
            'is_flexible' => false,
            'is_active' => true,
        ]);
        $day = Shift::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Day',
            'code' => 'DAYN',
            'starts_at' => '09:00:00',
            'ends_at' => '18:00:00',
            'break_minutes' => 0,
            'is_flexible' => false,
            'is_active' => true,
        ]);
        ShiftRoster::query()->create([
            'employee_id' => $dayPerson->id,
            'work_date' => '2026-11-04',
            'shift_id' => $night->id,
            'is_off' => false,
        ]);
        ShiftRoster::query()->create([
            'employee_id' => $dayPerson->id,
            'work_date' => '2026-11-01',
            'shift_id' => $day->id,
            'is_off' => false,
        ]);
        AttendanceDay::query()->create([
            'employee_id' => $dayPerson->id,
            'work_date' => '2026-11-04',
            'punch_in' => '22:20:00',
            'punch_out' => '07:00:00',
            'status' => 'present',
            'source' => 'manual',
        ]);
        AttendanceDay::query()->create([
            'employee_id' => $dayPerson->id,
            'work_date' => '2026-11-01',
            'punch_in' => '08:00:00',
            'punch_out' => '13:00:00',
            'status' => 'present',
            'source' => 'manual',
        ]);

        $rosterNight = $math->range($dayPerson, Carbon::parse('2026-11-04'), Carbon::parse('2026-11-04'));
        $this->assertSame('22:00–07:00', $rosterNight['cuts'][0]['window']);
        $this->assertSame(20, $rosterNight['cuts'][0]['minutes']);

        $sunday = $math->range($dayPerson, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-01'));
        $this->assertSame([], $sunday['cuts']);
        $this->assertSame('08:00–13:00', $sunday['days']['2026-11-01']['window']);

        $nightSunday = $math->range($nightPerson, Carbon::parse('2026-11-08'), Carbon::parse('2026-11-08'));
        $this->assertSame([], $nightSunday['cuts']);
        $this->assertSame('N', $nightSunday['days']['2026-11-08']['status']);
        $this->assertEqualsWithDelta(25000 / 30, $nightSunday['earned'], 0.02);

        $marked = app(AttendanceCalculator::class)->recalculate($nightPerson, Carbon::parse('2026-11-08'));
        $this->assertSame('weekly_off', $marked->status);

        EmployeeWorkHour::query()->create([
            'employee_id' => $dayPerson->id,
            'uses_department' => true,
            'shift_kind' => 'day',
            'works_sunday' => false,
        ]);
        $dayPerson->unsetRelation('workHour');
        $dayOff = $math->range($dayPerson, Carbon::parse('2026-11-15'), Carbon::parse('2026-11-15'));
        $this->assertSame([], $dayOff['cuts']);
        $this->assertSame('N', $dayOff['days']['2026-11-15']['status']);

        $nightPerson->workHour->update(['works_sunday' => true]);
        $nightPerson->unsetRelation('workHour');
        AttendanceDay::query()->create([
            'employee_id' => $nightPerson->id,
            'work_date' => '2026-11-22',
            'punch_in' => '21:30:00',
            'punch_out' => '02:00:00',
            'status' => 'present',
            'source' => 'manual',
        ]);
        $nightOn = $math->range($nightPerson, Carbon::parse('2026-11-22'), Carbon::parse('2026-11-22'));
        $this->assertSame('21:00–02:00', $nightOn['cuts'][0]['window']);
        $this->assertSame(30, $nightOn['cuts'][0]['minutes']);
    }

    private function structure(array $org): SalaryStructure
    {
        $head = SalaryHead::query()->create([
            'company_id' => $org['company']->id,
            'name' => 'Basic',
            'code' => 'BASIC',
            'category' => 'earning',
            'method' => 'percent_gross',
            'value' => 100,
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
            'value' => 100,
        ]);

        return $structure;
    }

    private function paySalary($employee, SalaryStructure $structure, float $amount): void
    {
        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'gross_monthly' => $amount,
            'effective_from' => '2026-01-01',
        ]);
    }

    private function fillMonth($employee, int $year, int $month): void
    {
        $cursor = Carbon::create($year, $month, 1)->startOfDay();
        $end = $cursor->copy()->endOfMonth();
        while ($cursor->lte($end)) {
            $sunday = $cursor->dayOfWeek === 0;
            AttendanceDay::query()->create([
                'employee_id' => $employee->id,
                'work_date' => $cursor->toDateString(),
                'punch_in' => $sunday ? '08:00:00' : '09:00:00',
                'punch_out' => $sunday ? '13:00:00' : '18:00:00',
                'status' => 'present',
                'source' => 'manual',
            ]);
            $cursor->addDay();
        }
    }
}
