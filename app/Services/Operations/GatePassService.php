<?php

namespace App\Services\Operations;

use App\Models\Employee;
use App\Models\GatePass;
use App\Models\GatePassSetting;
use App\Models\User;
use App\Notifications\GatePassNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GatePassService
{
    public function create(User $actor, array $data, Employee $forEmployee): GatePass
    {
        $settings = GatePassSetting::forCompany((int) $forEmployee->company_id);
        $self = $actor->employee;
        $isSelf = $self && (int) $self->id === (int) $forEmployee->id;
        $early = ! empty($data['is_early_leave']);

        $this->assertCanCreate($actor, $self, $forEmployee, $isSelf, $settings);
        if (! $early && (int) $data['duration_minutes'] > $settings->max_duration_minutes) {
            throw ValidationException::withMessages(['duration_minutes' => 'Duration exceeds the maximum allowed limit.']);
        }

        GatePass::expireStale();
        $this->assertNoDuplicate($forEmployee, $settings);

        $auto = $this->shouldAutoApprove($actor, $self, $forEmployee, $isSelf, $settings);

        return DB::transaction(function () use ($actor, $forEmployee, $data, $auto, $early) {
            $pass = GatePass::query()->create([
                'company_id' => $forEmployee->company_id,
                'number' => GatePass::generateNumber(),
                'employee_id' => $forEmployee->id,
                'created_by' => $actor->id,
                'date' => $data['date'],
                'out_time' => $data['out_time'],
                'duration_minutes' => (int) $data['duration_minutes'],
                'is_early_leave' => $early,
                'work_type' => $data['work_type'] === GatePass::WORK_OFFICE ? GatePass::WORK_OFFICE : GatePass::WORK_PERSONAL,
                'reason' => $data['reason'],
                'status' => $auto ? GatePass::STATUS_APPROVED : GatePass::STATUS_PENDING,
                'decided_by' => $auto ? $actor->id : null,
                'decided_at' => $auto ? now() : null,
                'expires_at' => GatePass::calculateExpiresAt($data['date'], $data['out_time'], (int) $data['duration_minutes']),
            ]);

            $this->notify($pass, $auto ? 'approved' : 'requested');

            return $pass->fresh(['employee.department', 'decider', 'creator']);
        });
    }

    public function approve(GatePass $pass, User $actor): GatePass
    {
        $this->assertCanDecide($pass, $actor);
        $pass->update([
            'status' => GatePass::STATUS_APPROVED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => null,
            'expires_at' => $pass->expiresAtFromOut(),
        ]);

        $pass = $pass->fresh(['employee.department', 'decider']);
        $this->notify($pass, 'approved');

        return $pass;
    }

    public function reject(GatePass $pass, User $actor, ?string $note = null): GatePass
    {
        $this->assertCanDecide($pass, $actor);
        $pass->update([
            'status' => GatePass::STATUS_REJECTED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        $pass = $pass->fresh(['employee.department', 'decider']);
        $this->notify($pass, 'rejected');

        return $pass;
    }

    protected function shouldAutoApprove(User $actor, ?Employee $self, Employee $for, bool $isSelf, GatePassSetting $settings): bool
    {
        if ($isSelf && $self?->isSupervisor()) {
            return false;
        }
        if ($isSelf && $self?->isChild()) {
            return ! $settings->require_parent_approval;
        }

        return ($self && $self->manages($for)) || $actor->isGatePassAdmin();
    }

    protected function assertCanCreate(User $actor, ?Employee $self, Employee $for, bool $isSelf, GatePassSetting $settings): void
    {
        if ($isSelf && $self?->isChild() && ! $settings->allow_self_apply) {
            throw ValidationException::withMessages(['date' => 'Self Gate Pass applications are currently disabled.']);
        }

        if (! $isSelf && ! $actor->isGatePassAdmin() && ! ($self && $self->manages($for))) {
            throw ValidationException::withMessages(['employee_id' => 'You are not authorized to create a Gate Pass for this employee.']);
        }
    }

    protected function assertNoDuplicate(Employee $employee, GatePassSetting $settings): void
    {
        if (GatePass::query()->forEmployees([$employee->id])->pending()->exists()) {
            throw ValidationException::withMessages(['date' => 'A pending Gate Pass request already exists for this employee.']);
        }

        if ($settings->prevent_duplicate_active && GatePass::query()->forEmployees([$employee->id])->active()->exists()) {
            throw ValidationException::withMessages(['date' => 'This employee already has an active Gate Pass.']);
        }
    }

    protected function assertCanDecide(GatePass $pass, User $actor): void
    {
        if (! $pass->isPending()) {
            throw ValidationException::withMessages(['status' => 'Only pending Gate Passes can be decided.']);
        }

        if (! $pass->employee || ! $actor->canDecideGatePassFor($pass->employee)) {
            throw ValidationException::withMessages(['status' => 'You are not authorized to perform this action.']);
        }
    }

    protected function notify(GatePass $pass, string $event): void
    {
        $recipients = $event === 'requested'
            ? $this->requestRecipients($pass)
            : collect([$pass->employee?->user])->filter();

        $recipients->unique('id')->each(fn (User $user) => $user->notify(new GatePassNotice($pass, $event)));
    }

    protected function requestRecipients(GatePass $pass)
    {
        $employee = $pass->employee;
        $ancestors = $employee?->ancestorChain()->map->user->filter() ?? collect();

        return $ancestors->isNotEmpty()
            ? $ancestors
            : User::query()->where('is_active', true)->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super-admin', 'hr-admin']))->get();
    }
}
