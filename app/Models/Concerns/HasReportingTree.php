<?php

namespace App\Models\Concerns;

use App\Models\Employee;
use Illuminate\Support\Collection;

trait HasReportingTree
{
    public function ancestorChain(): Collection
    {
        $ancestors = collect();
        $currentId = $this->reporting_manager_id;
        $seen = collect();

        while ($currentId && ! $seen->contains($currentId) && $ancestors->count() < 20) {
            $seen->push($currentId);
            $parent = static::query()->find($currentId);
            if (! $parent) {
                break;
            }
            $ancestors->push($parent);
            $currentId = $parent->reporting_manager_id;
        }

        return $ancestors;
    }

    public function managedEmployeeIds(): Collection
    {
        $ids = collect();
        $seen = collect([$this->id]);
        $frontier = collect([$this->id]);

        while ($frontier->isNotEmpty()) {
            $next = static::query()
                ->whereIn('reporting_manager_id', $frontier)
                ->pluck('id')
                ->diff($seen)
                ->values();

            if ($next->isEmpty()) {
                break;
            }

            $ids = $ids->merge($next);
            $seen = $seen->merge($next);
            $frontier = $next;
        }

        return $ids->unique()->values();
    }

    public function familyMemberIds(): Collection
    {
        return $this->managedEmployeeIds()->push($this->id)->unique()->values();
    }

    public function manages(Employee $employee): bool
    {
        return (int) $this->id !== (int) $employee->id
            && $this->managedEmployeeIds()->contains($employee->id);
    }

    public function isSupervisor(): bool
    {
        if ($this->canBeReportingManager()) {
            return true;
        }

        return $this->relationLoaded('directReports')
            ? $this->directReports->isNotEmpty()
            : $this->directReports()->exists();
    }

    public function isChild(): bool
    {
        return ! $this->isSupervisor();
    }
}
