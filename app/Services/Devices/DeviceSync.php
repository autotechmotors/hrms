<?php

namespace App\Services\Devices;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\AttendancePunchSkip;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\DeviceUser;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use Illuminate\Support\Collection;
use App\Services\Operations\AttendanceCalculator;
use App\Support\StoredImage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class DeviceSync
{
    /** Another face inside this window is the same visit, not a new clock in or clock out. */
    public const SAME_VISIT_SECONDS = 120;

    public function __construct(
        private readonly DahuaPush $push,
        private readonly DevicePeople $people,
        private readonly AttendanceCalculator $calculator,
    ) {}

    public function run(Device $device): string
    {
        app(PunchForward::class)->flush();
        $this->people->promoteMissingProfiles();
        $imported = $this->push->importInbox($device);
        $this->push->continueCollect($device);
        $created = $this->linkPeople($device);
        $this->pushUnlinked($device);
        $punches = $this->importPunches($device, true);
        $this->queueMissingBiometrics($device);
        $queued = false;
        if (! $this->push->listRequestWaiting($device)) {
            $this->push->queueUserList($device);
            $queued = true;
        }

        return $device->name.': imported '.$imported.', added '.$created.', punches '.$punches.($queued ? ', list requested' : ', list already waiting');
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

        $count = 0;
        foreach (Device::query()->orderBy('id')->get() as $device) {
            $count += $this->importPunches($device, false);
        }
        $this->reapplyRecent();

        return $count;
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

    private function queueMissingBiometrics(Device $device): void
    {
        $people = $device->terminalUsers()->whereNotNull('employee_id')->get();
        $this->queueKind($device, $people, 'face', 'query-faces');
        $this->queueKind($device, $people, 'finger', 'query-fingers');
        $this->queueKind($device, $people, 'card', 'query-cards');
    }

    private function queueKind(Device $device, $people, string $kind, string $action): void
    {
        if ($this->push->actionWaiting($device, $action)) {
            return;
        }

        $ids = [];
        foreach ($people as $person) {
            $has = EmployeeCredential::query()
                ->where('employee_id', $person->employee_id)
                ->where('kind', $kind)
                ->exists();
            if (! $has) {
                $ids[] = $person->user_id;
            }
            if (count($ids) >= 10) {
                break;
            }
        }
        if ($ids === []) {
            return;
        }

        $this->push->queueBiometricQuery($device, $action, $ids);
    }

    private function importPunches(Device $device, bool $recover): int
    {
        $dir = $this->push->directory().DIRECTORY_SEPARATOR.'saved_files';
        if (! is_dir($dir)) {
            return 0;
        }

        $count = 0;
        foreach (File::files($dir) as $file) {
            if (! str_ends_with(strtolower($file->getFilename()), '.json')) {
                continue;
            }
            $data = json_decode(File::get($file->getPathname()), true);
            if (! is_array($data) || ! isset($data['NotifyInfo']['Events'])) {
                continue;
            }
            $count += $this->importEventFile($device, $data, $file->getFilenameWithoutExtension(), $dir);
            $this->archiveEvent($dir, $file->getFilename());
        }

        return $recover ? $count + $this->recoverImported($device, $dir) : $count;
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

        $saved = $this->importEventFile($device, $data, $seq, '', true, $image, true);

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

    private function recoverImported(Device $device, string $dir): int
    {
        $imported = $dir.DIRECTORY_SEPARATOR.'imported';
        if (! is_dir($imported)) {
            return 0;
        }

        $count = 0;
        foreach (File::files($imported) as $file) {
            if (! str_ends_with(strtolower($file->getFilename()), '.json')) {
                continue;
            }
            $data = json_decode(File::get($file->getPathname()), true);
            if (! is_array($data) || ! isset($data['NotifyInfo']['Events'])) {
                continue;
            }
            $count += $this->importEventFile($device, $data, $file->getFilenameWithoutExtension(), $dir, true);
        }

        return $count;
    }

    private function archiveEvent(string $dir, string $filename): void
    {
        $from = $dir.DIRECTORY_SEPARATOR.$filename;
        if (! is_file($from)) {
            return;
        }
        $folder = $dir.DIRECTORY_SEPARATOR.'imported';
        File::ensureDirectoryExists($folder);
        $to = $folder.DIRECTORY_SEPARATOR.$filename;
        if (is_file($to)) {
            File::delete($to);
        }
        File::move($from, $to);
    }

    private function importEventFile(Device $device, array $data, string $seq, string $dir, bool $knownOnly = false, ?string $storedImage = null, bool $useStoredImage = false): int
    {
        $saved = 0;
        $image = $useStoredImage ? $storedImage : $this->imageFor($dir, $seq);
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
            $direction = $this->direction((string) ($row['Type'] ?? 'Entry'));
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
                    'image' => $image,
                ],
            );
            if ($punch->wasRecentlyCreated) {
                $saved++;
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

    private function pushUnlinked(Device $device): void
    {
        $linked = DeviceTerminalUser::query()->where('device_id', $device->id)->pluck('employee_id')->filter();
        Employee::query()
            ->where('company_id', $device->company_id)
            ->when($linked->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $linked))
            ->orderBy('id')
            ->each(function (Employee $employee): void {
                app(EmployeePush::class)->sync($employee);
            });
    }

    private function reapplyRecent(): void
    {
        $groups = AttendancePunch::query()
            ->where('punched_at', '>=', now()->subDays(2)->startOfDay())
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (AttendancePunch $punch) => $punch->employee_id.'|'.$punch->punched_at->timezone('Asia/Kolkata')->toDateString());

        foreach ($groups as $key => $unused) {
            [$employeeId, $date] = explode('|', (string) $key, 2);
            $employee = Employee::query()->find($employeeId);
            if ($employee) {
                $this->applyDay($employee, Carbon::parse($date, 'Asia/Kolkata'));
            }
        }
    }

    private function applyDay($employee, Carbon $date): void
    {
        $start = $date->copy()->timezone('Asia/Kolkata')->startOfDay();
        $punches = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('punched_at', [$start, $start->copy()->endOfDay()])
            ->orderBy('punched_at')
            ->orderBy('id')
            ->get();
        $clusters = $this->clusters($punches);

        foreach ($clusters as $index => $cluster) {
            $direction = $index % 2 === 0 ? 'in' : 'out';
            foreach ($cluster['punches'] as $punch) {
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

    private function imageFor(string $dir, string $seq): ?string
    {
        $path = $dir.DIRECTORY_SEPARATOR.$seq.'_Pic.jpg';
        if (! is_file($path)) {
            return null;
        }

        return StoredImage::normalize(base64_encode((string) file_get_contents($path)));
    }

    private function direction(string $type): string
    {
        return in_array(strtolower($type), ['exit', 'out'], true) ? 'out' : 'in';
    }
}
