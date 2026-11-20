<?php

namespace App\Services\Operations;

use App\Models\Employee;
use App\Models\EmployeeShiftSpan;
use App\Models\EmployeeWorkHour;
use App\Models\User;
use App\Services\AccessScope;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftSchedule
{
    /** @var array<int, Collection<int, EmployeeShiftSpan>> */
    private array $cache = [];

    public function canAssign(User $user, Employee $employee): bool
    {
        if (! app(AccessScope::class)->canAccessEmployee($user, $employee)) {
            return false;
        }

        if ($user->hasRole('super-admin') || $user->hasRole('hr-admin')) {
            return true;
        }

        $actor = $user->employee;

        return $actor !== null && $actor->manages($employee);
    }

    public function kindOn(Employee $employee, CarbonInterface $date): string
    {
        $span = $this->spanOn($employee, $date);

        if ($span) {
            return $span->kind === 'night' ? 'night' : 'day';
        }

        return ($employee->workHour?->shift_kind ?? 'day') === 'night' ? 'night' : 'day';
    }

    public function spanOn(Employee $employee, CarbonInterface $date): ?EmployeeShiftSpan
    {
        $on = Carbon::parse($date)->timezone('Asia/Kolkata')->startOfDay();

        return $this->spans($employee)->first(function (EmployeeShiftSpan $span) use ($on) {
            $start = $span->starts_on->copy()->startOfDay();
            $end = $span->ends_on?->copy()->startOfDay();

            return $start->lte($on) && ($end === null || $end->gte($on));
        });
    }

    /**
     * @param  list<array<string, mixed>>  $ranges
     */
    public function saveMonth(Employee $employee, int $year, int $month, ?string $continuous, array $ranges): void
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $label = $start->format('F Y');

        if ($continuous !== null && ! in_array($continuous, ['day', 'night'], true)) {
            throw ValidationException::withMessages([
                'continuous' => 'Choose Day or Night for all days.',
            ]);
        }

        $clean = $continuous === null ? $this->rangesFor($ranges, $start, $end, $label) : [];

        DB::transaction(function () use ($employee, $start, $end, $continuous, $clean): void {
            if ($continuous !== null) {
                $this->clearFrom($employee, $start);
                EmployeeShiftSpan::query()->create([
                    'employee_id' => $employee->id,
                    'kind' => $continuous,
                    'starts_on' => $start->toDateString(),
                    'ends_on' => null,
                ]);
            } else {
                $this->clearBetween($employee, $start, $end);
                foreach ($clean as $range) {
                    EmployeeShiftSpan::query()->create([
                        'employee_id' => $employee->id,
                        'kind' => $range['kind'],
                        'starts_on' => $range['starts_on'],
                        'ends_on' => $range['ends_on'],
                    ]);
                }
            }

            $this->forget($employee);
            $employee->unsetRelation('workHour');
            $this->syncKind($employee, $this->kindOn($employee, now()));
        });
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     label: string,
     *     days: int,
     *     start: string,
     *     end: string,
     *     prev: array{year: int, month: int},
     *     next: array{year: int, month: int},
     *     continuous: ?string,
     *     saved: bool,
     *     ranges: list<array{kind: string, starts_on: string, ends_on: string}>,
     *     cells: list<array{date: string, day: int, kind: string, saved: bool}>
     * }
     */
    public function present(Employee $employee, int $year, int $month): array
    {
        $year = max(1990, min(2100, $year));
        $month = max(1, min(12, $month));
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $fallback = ($employee->workHour?->shift_kind ?? 'day') === 'night' ? 'night' : 'day';
        $ranges = [];
        $saved = false;
        $continuous = null;

        $cursor = $start->copy();
        $kinds = [];
        while ($cursor->lte($end)) {
            $span = $this->spanOn($employee, $cursor);
            $kinds[$cursor->toDateString()] = $span?->kind;
            if ($span) {
                $saved = true;
            }
            $cursor->addDay();
        }

        $openEnded = $this->spans($employee)->first(function (EmployeeShiftSpan $span) use ($start, $end) {
            return $span->ends_on === null
                && $span->starts_on->copy()->startOfDay()->lte($start)
                && $this->covers($span, $start, $end);
        });

        if ($openEnded && $this->onlyKind($kinds, $openEnded->kind)) {
            $continuous = $openEnded->kind;
        } elseif (! $saved) {
            $continuous = $fallback;
        } else {
            $ranges = $this->clipRanges($employee, $start, $end);
        }

        $cells = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $explicit = $kinds[$key] ?? null;
            $cells[] = [
                'date' => $key,
                'day' => $cursor->day,
                'kind' => $explicit ?: ($continuous ?: $fallback),
                'saved' => $explicit !== null,
            ];
            $cursor->addDay();
        }

        $prev = $start->copy()->subMonth();
        $next = $start->copy()->addMonth();

        return [
            'year' => $year,
            'month' => $month,
            'label' => $start->format('F Y'),
            'days' => $start->daysInMonth,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'prev' => ['year' => (int) $prev->year, 'month' => (int) $prev->month],
            'next' => ['year' => (int) $next->year, 'month' => (int) $next->month],
            'continuous' => $continuous,
            'saved' => $saved,
            'ranges' => $ranges,
            'cells' => $cells,
        ];
    }

    /**
     * @param  array<string, ?string>  $kinds
     */
    private function onlyKind(array $kinds, string $kind): bool
    {
        foreach ($kinds as $value) {
            if ($value !== $kind) {
                return false;
            }
        }

        return true;
    }

    private function covers(EmployeeShiftSpan $span, Carbon $start, Carbon $end): bool
    {
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $on = $cursor->copy()->startOfDay();
            $spanStart = $span->starts_on->copy()->startOfDay();
            $spanEnd = $span->ends_on?->copy()->startOfDay();
            if ($spanStart->gt($on) || ($spanEnd !== null && $spanEnd->lt($on))) {
                return false;
            }
            $cursor->addDay();
        }

        return true;
    }

    /**
     * @return list<array{kind: string, starts_on: string, ends_on: string}>
     */
    private function clipRanges(Employee $employee, Carbon $start, Carbon $end): array
    {
        $rows = [];
        $cursor = $start->copy();
        $openKind = null;
        $openStart = null;

        while ($cursor->lte($end)) {
            $kind = $this->spanOn($employee, $cursor)?->kind;
            if ($kind === null) {
                if ($openKind !== null) {
                    $rows[] = [
                        'kind' => $openKind,
                        'starts_on' => $openStart,
                        'ends_on' => $cursor->copy()->subDay()->toDateString(),
                    ];
                    $openKind = null;
                    $openStart = null;
                }
            } elseif ($kind !== $openKind) {
                if ($openKind !== null) {
                    $rows[] = [
                        'kind' => $openKind,
                        'starts_on' => $openStart,
                        'ends_on' => $cursor->copy()->subDay()->toDateString(),
                    ];
                }
                $openKind = $kind;
                $openStart = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        if ($openKind !== null) {
            $rows[] = [
                'kind' => $openKind,
                'starts_on' => $openStart,
                'ends_on' => $end->toDateString(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $ranges
     * @return list<array{kind: string, starts_on: string, ends_on: string}>
     */
    private function rangesFor(array $ranges, Carbon $start, Carbon $end, string $label): array
    {
        $clean = [];
        $taken = [];

        foreach ($ranges as $range) {
            if (! is_array($range)) {
                continue;
            }
            $kind = $range['kind'] ?? null;
            $fromRaw = $range['starts_on'] ?? null;
            $toRaw = $range['ends_on'] ?? null;
            if ($fromRaw === null || $fromRaw === '' || $toRaw === null || $toRaw === '') {
                continue;
            }
            if (! in_array($kind, ['day', 'night'], true)) {
                throw ValidationException::withMessages([
                    'ranges' => 'Each row needs Day or Night, a start date, and an end date.',
                ]);
            }
            $from = Carbon::parse($fromRaw)->startOfDay();
            $to = Carbon::parse($toRaw)->startOfDay();
            if ($from->lt($start) || $to->gt($end)) {
                throw ValidationException::withMessages([
                    'ranges' => 'Keep the dates inside '.$label.'.',
                ]);
            }
            if ($to->lt($from)) {
                throw ValidationException::withMessages([
                    'ranges' => 'The end date is before the start date.',
                ]);
            }
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $key = $cursor->toDateString();
                if (isset($taken[$key])) {
                    throw ValidationException::withMessages([
                        'ranges' => 'These dates are on Day and Night together. Each date needs one shift.',
                    ]);
                }
                $taken[$key] = $kind;
                $cursor->addDay();
            }
            $clean[] = [
                'kind' => $kind,
                'starts_on' => $from->toDateString(),
                'ends_on' => $to->toDateString(),
            ];
        }

        $open = $start->daysInMonth - count($taken);
        if ($clean === []) {
            throw ValidationException::withMessages([
                'continuous' => 'Turn Day or Night on. Both cannot be off.',
            ]);
        }
        if ($open > 0) {
            throw ValidationException::withMessages([
                'ranges' => 'Fill every day of '.$label.'. '.$open.' '.($open === 1 ? 'day is' : 'days are').' still open.',
            ]);
        }

        return $clean;
    }

    private function clearFrom(Employee $employee, Carbon $start): void
    {
        foreach ($this->spans($employee) as $span) {
            $spanStart = $span->starts_on->copy()->startOfDay();
            $spanEnd = $span->ends_on?->copy()->startOfDay();
            if ($spanEnd !== null && $spanEnd->lt($start)) {
                continue;
            }
            if ($spanStart->lt($start)) {
                $span->update(['ends_on' => $start->copy()->subDay()->toDateString()]);

                continue;
            }
            $span->delete();
        }
        $this->forget($employee);
    }

    private function clearBetween(Employee $employee, Carbon $start, Carbon $end): void
    {
        foreach ($this->spans($employee) as $span) {
            $spanStart = $span->starts_on->copy()->startOfDay();
            $spanEnd = $span->ends_on?->copy()->startOfDay();
            $overlaps = $spanStart->lte($end) && ($spanEnd === null || $spanEnd->gte($start));
            if (! $overlaps) {
                continue;
            }
            $startsBefore = $spanStart->lt($start);
            $endsAfter = $spanEnd === null || $spanEnd->gt($end);
            if ($startsBefore && $endsAfter) {
                $tailEnd = $span->ends_on?->toDateString();
                $span->update(['ends_on' => $start->copy()->subDay()->toDateString()]);
                EmployeeShiftSpan::query()->create([
                    'employee_id' => $employee->id,
                    'kind' => $span->kind,
                    'starts_on' => $end->copy()->addDay()->toDateString(),
                    'ends_on' => $tailEnd,
                ]);

                continue;
            }
            if ($startsBefore) {
                $span->update(['ends_on' => $start->copy()->subDay()->toDateString()]);

                continue;
            }
            if ($endsAfter) {
                $span->update(['starts_on' => $end->copy()->addDay()->toDateString()]);

                continue;
            }
            $span->delete();
        }
        $this->forget($employee);
    }

    private function syncKind(Employee $employee, string $kind): void
    {
        $row = EmployeeWorkHour::query()->firstOrNew(['employee_id' => $employee->id]);
        $row->shift_kind = $kind;
        if (! $row->exists) {
            $row->uses_department = true;
        }
        $row->save();
    }

    /**
     * @return Collection<int, EmployeeShiftSpan>
     */
    private function spans(Employee $employee): Collection
    {
        return $this->cache[$employee->id] ??= EmployeeShiftSpan::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();
    }

    private function forget(Employee $employee): void
    {
        unset($this->cache[$employee->id]);
    }
}
