<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\EmployeeSalary;
use App\Models\Holiday;
use App\Models\SalaryHead;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureLine;
use App\Services\Operations\MonthReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrganization;
use Tests\TestCase;

class MonthReportTest extends TestCase
{
    use BuildsOrganization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_the_month_counts_a_missing_punch_and_a_public_holiday(): void
    {
        $org = $this->makeCompany('RPT');
        $employee = $this->makeEmployee($org, 'R1');
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
            'code' => 'RMON',
            'is_active' => true,
        ]);
        SalaryStructureLine::query()->create([
            'salary_structure_id' => $structure->id,
            'salary_head_id' => $head->id,
            'value' => 100,
        ]);
        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'gross_monthly' => 30000,
            'effective_from' => '2026-01-01',
        ]);
        Holiday::query()->create([
            'company_id' => $org['company']->id,
            'holiday_date' => '2026-04-02',
            'name' => 'Closed',
            'kind' => 'public',
        ]);

        $cursor = Carbon::parse('2026-04-01')->startOfDay();
        $end = $cursor->copy()->endOfMonth();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            if (! in_array($key, ['2026-04-02', '2026-04-06'], true)) {
                $sunday = $cursor->dayOfWeek === 0;
                AttendanceDay::query()->create([
                    'employee_id' => $employee->id,
                    'work_date' => $key,
                    'punch_in' => $sunday ? '08:00:00' : '09:00:00',
                    'punch_out' => $sunday ? '13:00:00' : '18:00:00',
                    'status' => 'present',
                    'source' => 'manual',
                ]);
            }
            $cursor->addDay();
        }

        $hr = $this->makeUser('hr-admin', $org['company']);
        $report = app(MonthReport::class)->make($hr, (int) $org['company']->id, Carbon::parse('2026-04-01'));

        $this->assertSame(1, $report['people']);
        $this->assertSame(30, $report['calendar_days']);
        $this->assertSame(1, $report['public_holidays']);
        $this->assertSame(1, $report['holidays']);
        $this->assertSame(1, $report['absent']);
        $this->assertSame(28, $report['present']);
        $this->assertEqualsWithDelta(1000, $report['cutoff'], 0.02);

        $this->actingAsScoped($hr)
            ->get(route('reports.index', ['year' => 2026, 'month' => 4]))
            ->assertOk()
            ->assertSee('Absent days')
            ->assertSee('Salary cutoff');
    }
}
