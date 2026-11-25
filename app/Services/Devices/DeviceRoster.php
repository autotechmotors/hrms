<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DeviceRoster
{
    public function __construct(
        private readonly HikvisionCredentials $credentials,
        private readonly EmployeePush $push,
    ) {}

    /**
     * One short step of a sync. The browser calls this again until done,
     * so a shared host does not kill the page.
     *
     * @return array{offset: int, total: int, done: bool, added: int, faces: int, fingers: int, copied: int, failed: int}
     */
    public function syncBatch(Device $source, int $offset, int $limit = 3): array
    {
        $key = 'hikvision.roster.'.$source->id;
        $cached = Cache::get($key);
        if (! is_array($cached)) {
            $cached = $this->credentials->listing($source);
            Cache::put($key, $cached, now()->addMinutes(30));

            return $this->batchResult(0, count($cached), count($cached) === 0, 0, 0, 0, 0, 0, 'listed');
        }

        $people = array_values($cached);
        $total = count($people);
        $slice = array_slice($people, max(0, $offset), max(1, $limit));
        if ($slice === []) {
            Cache::forget($key);

            return $this->batchResult($total, $total, true, 0, 0, 0, 0, 0, 'copy');
        }
        $added = 0;
        $faces = 0;
        $fingers = 0;
        $copied = 0;
        $failed = 0;
        $targets = Device::query()
            ->where('company_id', $source->company_id)
            ->where('id', '!=', $source->id)
            ->get()
            ->filter(fn (Device $device) => $device->isHikvision())
            ->values();

        foreach ($slice as $person) {
            if (! is_array($person) || trim((string) ($person['code'] ?? '')) === '') {
                continue;
            }
            $row = $this->credentials->take($source, $person, true);
            $added += $row['added'];
            $faces += $row['faces'];
            $fingers += $row['fingers'];
            $employee = Employee::query()
                ->where('company_id', $source->company_id)
                ->where('employee_code', $person['code'])
                ->first();
            if (! $employee) {
                continue;
            }
            foreach ($targets as $target) {
                try {
                    $this->push->rewrite($target, $employee, (string) $person['code'], (string) $employee->full_name);
                    DeviceTerminalUser::query()->updateOrCreate(
                        ['device_id' => $target->id, 'user_id' => (string) $person['code']],
                        ['name' => (string) $employee->full_name, 'employee_id' => $employee->id, 'dismissed_at' => null],
                    );
                    $copied++;
                } catch (Throwable) {
                    $failed++;
                }
            }
        }

        $next = $offset + count($slice);
        $done = $next >= $total;
        if ($done) {
            Cache::forget($key);
        }

        return $this->batchResult($next, $total, $done, $added, $faces, $fingers, $copied, $failed, 'copy');
    }

    /**
     * @return array{offset: int, total: int, done: bool, added: int, faces: int, fingers: int, copied: int, failed: int, phase: string}
     */
    private function batchResult(int $offset, int $total, bool $done, int $added, int $faces, int $fingers, int $copied, int $failed, string $phase): array
    {
        return [
            'offset' => $offset,
            'total' => $total,
            'done' => $done,
            'added' => $added,
            'faces' => $faces,
            'fingers' => $fingers,
            'copied' => $copied,
            'failed' => $failed,
            'phase' => $phase,
        ];
    }

    public function sync(Device $source): array
    {
        $imported = $this->credentials->import($source, true);
        $targets = Device::query()
            ->where('company_id', $source->company_id)
            ->where('id', '!=', $source->id)
            ->get()
            ->filter(fn (Device $device) => $device->isHikvision())
            ->values();
        $copied = 0;
        $failed = 0;
        $people = DeviceTerminalUser::query()
            ->where('device_id', $source->id)
            ->whereNull('dismissed_at')
            ->whereNotNull('employee_id')
            ->with('employee')
            ->get();

        foreach ($people as $person) {
            $employee = $person->employee;
            if (! $employee instanceof Employee) {
                continue;
            }
            foreach ($targets as $target) {
                try {
                    $this->push->rewrite($target, $employee, (string) $person->user_id, (string) $employee->full_name);
                    DeviceTerminalUser::query()->updateOrCreate(
                        ['device_id' => $target->id, 'user_id' => (string) $person->user_id],
                        ['name' => (string) $employee->full_name, 'employee_id' => $employee->id, 'dismissed_at' => null],
                    );
                    $copied++;
                } catch (Throwable) {
                    $failed++;
                }
            }
        }

        return [
            'added' => $imported['added'],
            'faces' => $imported['faces'],
            'fingers' => $imported['fingers'],
            'copied' => $copied,
            'failed' => $failed,
        ];
    }
}
