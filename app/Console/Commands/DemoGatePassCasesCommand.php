<?php

namespace App\Console\Commands;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\GatePass;
use App\Models\User;
use App\Services\Operations\AttendanceCalculator;
use App\Services\Operations\GatePassService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class DemoGatePassCasesCommand extends Command
{
    protected $signature = 'gatepass:demo-cases';

    protected $description = 'Create one demo Gate Pass per workflow case and print the hour / machine report';

    public function handle(GatePassService $passes, AttendanceCalculator $calculator): int
    {
        $day = now()->timezone('Asia/Kolkata')->toDateString();
        $this->forgetOldCases();

        $rows = [];
        foreach ($this->cases($day) as $case) {
            $rows[] = $this->runCase($passes, $calculator, $case, $day);
        }

        $this->table(
            ['Case', 'Employee', 'Type', 'Status', 'Machine', 'Worked', 'Away', 'Day'],
            $rows
        );

        return self::SUCCESS;
    }

    private function forgetOldCases(): void
    {
        $ids = GatePass::query()->where('reason', 'like', 'CASE:%')->pluck('id');
        AttendancePunch::query()->whereIn('gate_pass_id', $ids)->delete();
        GatePass::query()->whereIn('id', $ids)->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cases(string $day): array
    {
        $arif = $this->byCode('ATS319');
        $sahil = $this->byCode('ATS065');
        $kajal = $this->byCode('ATS279');
        $hitesh = $this->byCode('AUTO004');
        $pankaj = $this->byCode('ATB001');
        $manish = $this->byCode('AUTO003');
        $hr = User::query()->where('email', 'hr@hrms.test')->first();

        return [
            [
                'title' => '1 Pending — machine closed',
                'actor' => $arif?->user, 'for' => $arif, 'decider' => $manish?->user,
                'work' => 'personal', 'then' => 'pending',
                'reason' => 'CASE: pending parent approval',
                'out' => '15:00', 'in' => '09:00', 'leave' => '18:00',
            ],
            [
                'title' => '2 Rejected — machine closed',
                'actor' => $sahil?->user, 'for' => $sahil, 'decider' => $manish?->user,
                'work' => 'office', 'then' => 'reject', 'note' => 'Need the visit letter first.',
                'reason' => 'CASE: rejected by parent',
                'out' => '15:00', 'in' => '09:00', 'leave' => '18:00',
            ],
            [
                'title' => '3 Personal early return',
                'actor' => $kajal?->user, 'for' => $kajal, 'decider' => $manish?->user,
                'work' => 'personal', 'then' => 'approve',
                'reason' => 'CASE: personal 1h, back 1 min early',
                'out' => '14:00', 'gate_out' => '14:00', 'gate_in' => '14:59',
                'in' => '09:00', 'leave' => '18:00',
            ],
            [
                'title' => '4 Office work counts',
                'actor' => $hitesh?->user, 'for' => $hitesh, 'decider' => $hr,
                'work' => 'office', 'then' => 'approve',
                'reason' => 'CASE: office 1h counts as work',
                'out' => '14:00', 'gate_out' => '14:00', 'gate_in' => '15:00',
                'in' => '09:00', 'leave' => '18:00',
            ],
            [
                'title' => '5 Expired still out → half day',
                'actor' => $pankaj?->user, 'for' => $pankaj, 'decider' => $hr,
                'work' => 'personal', 'then' => 'expire_out',
                'reason' => 'CASE: expired still out half day',
                'out' => '14:00', 'gate_out' => '14:00',
                'in' => '09:00', 'leave' => null,
            ],
            [
                'title' => '6 Return after expire',
                'actor' => $hr, 'for' => $this->byCode('AUTO007') ?: $this->byCode('AUTO009'), 'decider' => $hr,
                'work' => 'personal', 'then' => 'return_late',
                'reason' => 'CASE: expired then returned, clock from arrival',
                'out' => '14:00', 'gate_out' => '14:00', 'gate_in' => '16:05',
                'in' => '09:00', 'leave' => '18:00',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $case
     * @return list<string>
     */
    private function runCase(GatePassService $passes, AttendanceCalculator $calculator, array $case, string $day): array
    {
        $employee = $case['for'];
        $actor = $case['actor'];
        if (! $employee || ! $actor) {
            return [$case['title'], 'skipped', '—', '—', '—', '—', '—', 'missing person'];
        }

        GatePass::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [GatePass::STATUS_PENDING, GatePass::STATUS_APPROVED])
            ->update(['status' => GatePass::STATUS_REJECTED, 'decision_note' => 'Cleared for CASE demo', 'machine_status' => 'revoked']);

        try {
            $pass = $passes->create($actor, [
                'date' => $day,
                'out_time' => $case['out'],
                'duration_minutes' => 60,
                'is_early_leave' => false,
                'work_type' => $case['work'],
                'reason' => $case['reason'],
            ], $employee);
        } catch (ValidationException $e) {
            return [$case['title'], $employee->full_name, $case['work'], 'error', '—', '—', '—', $e->getMessage()];
        }

        if ($case['then'] === 'approve' && ($case['decider'] ?? null) && $pass->isPending()) {
            $pass = $passes->approve($pass, $case['decider']);
        }
        if ($case['then'] === 'reject' && ($case['decider'] ?? null) && $pass->isPending()) {
            $pass = $passes->reject($pass, $case['decider'], $case['note'] ?? 'Rejected for demo.');
        }
        if (in_array($case['then'], ['expire_out', 'return_late'], true)) {
            if ($pass->isPending() && ($case['decider'] ?? null)) {
                $pass = $passes->approve($pass, $case['decider']);
            }
            $pass->update([
                'out_at' => Carbon::parse($day.' '.$case['gate_out'], 'Asia/Kolkata'),
                'status' => GatePass::STATUS_EXPIRED,
                'expires_at' => Carbon::parse($day.' 15:00:00', 'Asia/Kolkata'),
                'machine_status' => 'revoked',
                'machine_note' => 'Expired while still out.',
            ]);
            if (($case['gate_in'] ?? null) && $case['then'] === 'return_late') {
                $pass->update(['in_at' => Carbon::parse($day.' '.$case['gate_in'], 'Asia/Kolkata')]);
            }
        }
        if (($case['gate_out'] ?? null) && $pass->isApproved()) {
            $pass->update([
                'out_at' => Carbon::parse($day.' '.$case['gate_out'], 'Asia/Kolkata'),
                'in_at' => isset($case['gate_in']) ? Carbon::parse($day.' '.$case['gate_in'], 'Asia/Kolkata') : null,
            ]);
        }

        AttendanceDay::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $day],
            ['punch_in' => $case['in'].':00', 'punch_out' => $case['leave'] ? $case['leave'].':00' : null, 'status' => 'present', 'source' => 'device']
        );
        $dayRow = $calculator->recalculate($employee->fresh(), Carbon::parse($day, 'Asia/Kolkata'));
        $pass->refresh();

        return [
            $case['title'],
            $employee->full_name,
            $pass->work_type_label,
            $pass->status_label,
            $pass->machine_label,
            (string) $dayRow->minutes_worked,
            (string) $dayRow->minutes_away,
            str_replace('_', ' ', $dayRow->status),
        ];
    }

    private function byCode(string $code): ?Employee
    {
        return Employee::query()->where('employee_code', $code)->first();
    }
}
