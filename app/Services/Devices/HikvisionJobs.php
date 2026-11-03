<?php

namespace App\Services\Devices;

use App\Models\Employee;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class HikvisionJobs
{
    public function __construct(private readonly DevicePeople $people) {}

    /**
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    public function dispatchAndWait(array $job, int $seconds): array
    {
        $id = $this->push($job);

        return $this->wait($id, $seconds);
    }

    /**
     * @param  array<string, mixed>  $job
     */
    public function push(array $job): string
    {
        $id = (string) Str::uuid();
        $job['id'] = $id;
        $job['status'] = 'pending';
        $job['created_at'] = time();
        Cache::put($this->key($id), $job, now()->addHours(3));
        $ids = Cache::get('hikvision.jobs', []);
        $ids[] = $id;
        Cache::put('hikvision.jobs', array_values(array_unique($ids)), now()->addHours(3));

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claim(): ?array
    {
        if (! Cache::add('hikvision.jobs.claiming', 1, 5)) {
            return null;
        }
        try {
            $ids = array_values(array_filter((array) Cache::get('hikvision.jobs', [])));
            foreach ($ids as $id) {
                $job = Cache::get($this->key((string) $id));
                if (! is_array($job)) {
                    continue;
                }
                if (($job['status'] ?? '') === 'running' && (int) ($job['started_at'] ?? 0) < time() - 90) {
                    $job['status'] = 'pending';
                }
                if (($job['status'] ?? '') !== 'pending') {
                    continue;
                }
                $job['status'] = 'running';
                $job['started_at'] = time();
                Cache::put($this->key((string) $id), $job, now()->addHours(3));

                return $job;
            }

            return null;
        } finally {
            Cache::forget('hikvision.jobs.claiming');
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function finish(string $id, array $result): array
    {
        $job = Cache::get($this->key($id));
        if (! is_array($job)) {
            throw new RuntimeException('This terminal job is no longer waiting.');
        }
        $ok = (bool) ($result['ok'] ?? false);
        $job['status'] = $ok ? 'done' : 'failed';
        $job['message'] = (string) ($result['message'] ?? '');
        if ($ok) {
            $this->keep($job, $result);
        }
        Cache::put($this->key($id), $job, now()->addHours(3));

        return $job;
    }

    /**
     * @return array<string, mixed>
     */
    public function wait(string $id, int $seconds): array
    {
        $until = time() + max(1, $seconds);
        do {
            $job = Cache::get($this->key($id));
            if (is_array($job) && in_array($job['status'] ?? '', ['done', 'failed'], true)) {
                return $job;
            }
            usleep(400000);
        } while (time() < $until);

        return is_array($job ?? null) ? $job : ['status' => 'pending', 'message' => 'The office link to the face terminal is not running.'];
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $result
     */
    private function keep(array $job, array $result): void
    {
        $employeeId = (int) ($job['employee_id'] ?? 0);
        if ($employeeId < 1) {
            return;
        }
        $employee = Employee::query()->find($employeeId);
        if (! $employee) {
            return;
        }
        $face = $result['face'] ?? null;
        if (is_string($face) && $face !== '') {
            $this->people->storeItem($employee, ['PhotoData' => [$face]]);
        }
        $finger = $result['finger'] ?? null;
        if (is_string($finger) && $finger !== '') {
            $this->people->storeItem($employee, ['FingerPrints' => [[
                'FingerprintID' => (int) ($result['slot'] ?? 1),
                'FingerprintData' => $finger,
            ]]]);
        }
    }

    private function key(string $id): string
    {
        return 'hikvision.job.'.$id;
    }
}
