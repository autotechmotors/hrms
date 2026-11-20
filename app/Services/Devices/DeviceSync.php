<?php

namespace App\Services\Devices;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\AttendancePunchSkip;
use App\Models\AttendanceRule;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\DeviceUser;
use App\Models\Employee;
use App\Services\Operations\AttendanceCalculator;
use App\Services\Operations\GatePassAccess;
use App\Services\Operations\WorkHourResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DeviceSync
{
    /** Another face inside this window is the same visit, not a new clock in or clock out. */
    public const SAME_VISIT_SECONDS = 120;

    public function __construct(
        private readonly DevicePeople $people,
        private readonly AttendanceCalculator $calculator,
        private readonly WorkHourResolver $hours,
    ) {}

    public function run(Device $device): string
    {
        app(PunchForward::class)->flush();
        if (! $device->isHikvision()) {
            return $device->name.': skipped (Hikvision terminals only)';
        }

        $this->people->promoteMissingProfiles();
        $created = $this->linkPeople($device);

        return $device->name.': Hikvision people '.$created;
    }

    public function pullPunches(): int
    {
        app(PunchForward::class)->flush();
        try {
            if (! Cache::add('devices.pull-punches', 1, now()->addSeconds(8))) {
                return 0;
            }
        } catch (\Throwable) {
            // A cache miss must not block a punch import.
        }

        $this->reapplyRecent();

        return 0;
    }

    private function linkPeople(Device $device): int
    {
        $created = 0;
        foreach ($device->terminalUsers()->whereNull('employee_id')->whereNull('dismissed_at')->get() as $person) {
            $employee = $this->people->ensure($device, $person->user_id, (string) $person->name);
            if (! $employee) {
                continue;
            }
            $person->update(['employee_id' => $employee->id]);
            DeviceUser::query()->updateOrCreate(
                ['device_id' => $device->id, 'employee_id' => $employee->id],
                ['is_blocked' => false],
            );
            if ($employee->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{saved: int, pending: bool}
     */
    public function receive(Device $device, array $data, string $seq, ?string $image): array
    {
        $pending = false;
        foreach ($data['NotifyInfo']['Events'] ?? [] as $event) {
            if (! is_array($event)) {
                continue;
            }
            $row = $event['Data'] ?? null;
            if (! is_array($row)) {
                continue;
            }
            $userId = trim((string) ($row['UserID'] ?? ''));
            if ($userId === '') {
                continue;
            }
            if (! $this->linkExisting($device, $userId, (string) ($row['UserName'] ?? $userId))) {
                $pending = true;
            }
        }

        $saved = $this->importEventFile($device, $data, $seq, true, $image);

        return ['saved' => $saved, 'pending' => $pending && $saved === 0];
    }

    private function linkExisting(Device $device, string $userId, string $name): bool
    {
        $dismissed = DeviceTerminalUser::query()
            ->where('device_id', $device->id)
            ->where('user_id', $userId)
            ->whereNotNull('dismissed_at')
            ->exists();
        if ($dismissed) {
            return true;
        }

        $person = DeviceTerminalUser::query()
            ->where('device_id', $device->id)
            ->where('user_id', $userId)
            ->whereNull('dismissed_at')
            ->first();
        if ($person?->employee_id) {
            return true;
        }

        $employee = Employee::query()
            ->where('company_id', $device->company_id)
            ->where('employee_code', $userId)
            ->first();
        if (! $employee) {
            return false;
        }

        if ($person) {
            $person->update(['employee_id' => $employee->id, 'name' => $name]);

            return true;
        }

        DeviceTerminalUser::query()->create([
            'device_id' => $device->id,
            'user_id' => $userId,
            'name' => $name,
            'employee_id' => $employee->id,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importEventFile(Device $device, array $data, string $seq, bool $knownOnly = false, ?string $image = null): int
    {
        $saved = 0;
        foreach ($data['NotifyInfo']['Events'] as $event) {
            if (! is_array($event) || ($event['Code'] ?? '') !== 'AccessControl') {
                continue;
            }
            $row = $event['Data'] ?? null;
            if (! is_array($row) || (int) ($row['ErrorCode'] ?? 0) !== 0) {
                continue;
            }
            $userId = trim((string) ($row['UserID'] ?? ''));
            $utc = (int) ($row['RealUTC'] ?? $row['UTC'] ?? 0);
            if ($userId === '' || $utc <= 0) {
                continue;
            }
            if (($data['RegisterID'] ?? $device->register_id) !== $device->register_id && ($row['SN'] ?? '') !== $device->register_id) {
                continue;
            }

            if ($knownOnly) {
                $person = DeviceTerminalUser::query()
                    ->where('device_id', $device->id)
                    ->where('user_id', $userId)
                    ->whereNull('dismissed_at')
                    ->whereNotNull('employee_id')
                    ->first();
                if (! $person) {
                    continue;
                }
            } else {
                $person = DeviceTerminalUser::query()->firstOrCreate(
                    ['device_id' => $device->id, 'user_id' => $userId],
                    ['name' => $row['UserName'] ?? $userId],
                );
            }
            if ($person->dismissed_at) {
                continue;
            }
            $employee = $person->employee ?: ($knownOnly ? null : $this->people->ensure($device, $userId, (string) ($row['UserName'] ?? $person->name)));
            if (! $employee) {
                continue;
            }
            if (! $person->employee_id) {
                $person->update(['employee_id' => $employee->id, 'name' => $row['UserName'] ?? $person->name]);
            }

            $at = Carbon::createFromTimestampUTC($utc)->timezone('Asia/Kolkata');
            $direction = match ($device->punch_role) {
                Device::ROLE_IN => 'in',
                Device::ROLE_OUT => 'out',
                default => $this->direction((string) ($row['Type'] ?? 'Entry')),
            };
            $key = $device->register_id.'|'.$seq.'|'.$userId.'|'.$utc.'|'.$direction;
            if (AttendancePunchSkip::query()->where('external_key', $key)->exists()) {
                continue;
            }
            $punch = AttendancePunch::query()->firstOrCreate(
                ['external_key' => $key],
                [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'punched_at' => $at,
                    'direction' => $direction,
                    'image' => $this->storeSelfie($device) ? $image : null,
                ],
            );
            if ($punch->wasRecentlyCreated) {
                $access = app(GatePassAccess::class);
                if ($direction === 'out' && ! $device->isGate() && ! $access->outAllowed($employee, $at)) {
                    $punch->delete();
                    DeviceLog::moment('out denied '.$employee->employee_code, [
                        'device' => $device->register_id,
                        'employee_id' => $employee->id,
                        'at' => $at->toDateTimeString(),
                    ]);

                    continue;
                }
                $allowed = $access->attachPunch($employee, $punch, $device);
                if (! $allowed) {
                    $punch->delete();
                    DeviceLog::moment('gate denied '.$employee->employee_code, [
                        'device' => $device->register_id,
                        'employee_id' => $employee->id,
                        'at' => $at->toDateTimeString(),
                    ]);

                    continue;
                }
                $saved++;
                DeviceLog::moment($direction.' '.$employee->employee_code, [
                    'device' => $device->register_id,
                    'employee_id' => $employee->id,
                    'at' => $at->toDateTimeString(),
                    'seq' => $seq,
                ]);
                $this->applyDay($employee, $at->copy()->startOfDay());
                if (! $knownOnly) {
                    app(PunchForward::class)->send($device, $event, $seq, $image);
                }
            }
        }

        return $saved;
    }

    /**
     * @param  Collection<int, AttendancePunch>  $punches
     * @return Collection<int, AttendancePunch>
     */
    public function collapse(Collection $punches): Collection
    {
        return $this->clusters($punches)
            ->map(fn (array $cluster) => $cluster['punches'][0])
            ->values();
    }

    /**
     * @param  Collection<int, AttendancePunch>  $punches
     * @return Collection<int, array{start: Carbon, end: Carbon, punches: list<AttendancePunch>}>
     */
    private function clusters(Collection $punches): Collection
    {
        $clusters = [];
        foreach ($punches as $punch) {
            $at = $punch->punched_at->copy()->timezone('Asia/Kolkata');
            $last = count($clusters) - 1;
            if ($last >= 0 && ($at->getTimestamp() - $clusters[$last]['end']->getTimestamp()) < self::SAME_VISIT_SECONDS) {
                $clusters[$last]['punches'][] = $punch;
                $clusters[$last]['end'] = $at;

                continue;
            }
            $clusters[] = ['start' => $at->copy(), 'end' => $at->copy(), 'punches' => [$punch]];
        }

        return collect($clusters);
    }

    private function reapplyRecent(): void
    {
        $groups = AttendancePunch::query()
            ->select(['id', 'employee_id', 'punched_at', 'direction'])
            ->where('punched_at', '>=', now()->subDays(2)->startOfDay())
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (AttendancePunch $punch) => $punch->employee_id.'|'.$punch->punched_at->timezone('Asia/Kolkata')->toDateString());

        $keys = [];
        foreach ($groups as $key => $unused) {
            $keys[(string) $key] = true;
            [$employeeId, $date] = explode('|', (string) $key, 2);
            $previous = Carbon::parse($date, 'Asia/Kolkata')->subDay()->toDateString();
            $keys[$employeeId.'|'.$previous] = true;
        }

        foreach (array_keys($keys) as $key) {
            [$employeeId, $date] = explode('|', (string) $key, 2);
            $employee = Employee::query()->find($employeeId);
            if ($employee) {
                $this->applyDay($employee, Carbon::parse($date, 'Asia/Kolkata'));
            }
        }
    }

    public function rebuild(Employee $employee, Carbon $date): void
    {
        $this->applyDay($employee, $date);
    }

    private function applyDay($employee, Carbon $date): void
    {
        $start = $date->copy()->timezone('Asia/Kolkata')->startOfDay();
        $kept = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $start->toDateString())
            ->first();
        if ($kept?->source === 'manual') {
            $this->calculator->recalculate($employee, $date);

            return;
        }
        $span = $this->hours->attendanceSpan($employee, $start);
        $punches = AttendancePunch::query()
            ->with('device:id,punch_role')
            ->select(['id', 'employee_id', 'device_id', 'punched_at', 'direction', 'gate_pass_id'])
            ->where('employee_id', $employee->id)
            ->whereNull('gate_pass_id')
            ->where('punched_at', '>=', $span['from'])
            ->where('punched_at', '<', $span['to'])
            ->orderBy('punched_at')
            ->orderBy('id')
            ->get();
        $clusters = $this->clusters($punches);

        foreach ($clusters as $index => $cluster) {
            $direction = $index % 2 === 0 ? 'in' : 'out';
            foreach ($cluster['punches'] as $punch) {
                if (in_array($punch->device?->punch_role, [Device::ROLE_IN, Device::ROLE_OUT], true)) {
                    continue;
                }
                if ($punch->direction !== $direction) {
                    $punch->update(['direction' => $direction]);
                }
            }
        }

        $in = $clusters[0]['start'] ?? null;
        $out = null;
        for ($index = count($clusters) - 1; $index >= 1; $index--) {
            if ($index % 2 === 1) {
                $out = $clusters[$index]['start'];
                break;
            }
        }
        $day = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $start->toDateString())
            ->first() ?? new AttendanceDay([
                'employee_id' => $employee->id,
                'work_date' => $start->toDateString(),
            ]);
        $day->fill([
            'punch_in' => $in?->format('H:i:s'),
            'punch_out' => $out?->format('H:i:s'),
            'source' => 'device',
            'status' => $day->status ?: 'absent',
        ])->save();
        $this->calculator->recalculate($employee, $date);
    }

    private function storeSelfie(Device $device): bool
    {
        if (! $device->company_id) {
            return true;
        }

        $rule = AttendanceRule::query()->where('company_id', $device->company_id)->first();

        return $rule === null || $rule->store_selfie;
    }

    private function direction(string $type): string
    {
        return in_array(strtolower($type), ['exit', 'out'], true) ? 'out' : 'in';
    }
}
