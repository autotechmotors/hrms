<?php

namespace App\Services\Devices;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Support\StoredImage;
use Illuminate\Support\Facades\Storage;

class DevicePeople
{
    public function ensure(Device $device, string $userId, string $name): ?Employee
    {
        $existing = Employee::query()
            ->where('company_id', $device->company_id)
            ->where('employee_code', $userId)
            ->first();
        if ($existing) {
            $this->applyDeviceName($existing, $name);

            return $existing;
        }

        $removed = DeviceTerminalUser::query()
            ->where('user_id', $userId)
            ->whereNotNull('dismissed_at')
            ->whereHas('device', fn ($query) => $query->where('company_id', $device->company_id))
            ->exists();
        if ($removed) {
            return null;
        }

        $branch = Branch::query()->where('company_id', $device->company_id)->where('is_active', true)->orderBy('id')->first();
        $department = Department::query()->where('company_id', $device->company_id)->where('is_active', true)->orderBy('id')->first();
        $designation = Designation::query()->where('company_id', $device->company_id)->where('is_active', true)->orderBy('id')->first();
        $type = EmployeeType::query()->where('company_id', $device->company_id)->where('is_active', true)->orderBy('id')->first();
        $status = EmploymentStatus::query()->where('company_id', $device->company_id)->where('is_exit', false)->orderBy('id')->first();
        if (! $branch || ! $department || ! $designation || ! $type || ! $status) {
            return null;
        }

        [$first, $last] = $this->splitName($name !== '' ? $name : $userId);

        return Employee::query()->create([
            'company_id' => $device->company_id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'employee_type_id' => $type->id,
            'employment_status_id' => $status->id,
            'employee_code' => $userId,
            'first_name' => $first,
            'last_name' => $last,
            'joining_date' => now()->toDateString(),
            'is_blocked' => false,
        ]);
    }

    public function storeItem(Employee $employee, array $item): void
    {
        foreach (array_values(array_filter((array) ($item['PhotoData'] ?? []))) as $index => $photo) {
            $saved = $this->put($employee, 'face', (string) $index, (string) $photo);
            if ($saved) {
                $this->useAsProfile($employee, $saved);
            }
        }

        $prints = $item['FingerPrints'] ?? $item['FingerprintData'] ?? [];
        foreach (array_values((array) $prints) as $index => $print) {
            $value = is_array($print) ? (string) ($print['FingerprintData'] ?? $print['Data'] ?? $print['FingerPrintData'] ?? $print['Feature'] ?? '') : (string) $print;
            $slot = is_array($print) ? (string) ($print['FingerprintID'] ?? $print['FingerIndex'] ?? $index) : (string) $index;
            $this->put($employee, 'finger', $slot, $value);
        }

        $card = trim((string) ($item['CardNo'] ?? ''));
        if ($card !== '') {
            $this->put($employee, 'card', '0', base64_encode($card));
        }
    }

    public function promoteMissingProfiles(): void
    {
        Employee::query()
            ->whereHas('credentials', fn ($query) => $query->where('kind', 'face'))
            ->with(['credentials' => fn ($query) => $query->where('kind', 'face')->orderBy('slot')])
            ->orderBy('id')
            ->each(function (Employee $employee): void {
                if (is_string($employee->photograph_path) && str_contains($employee->photograph_path, '/device-face.') && Storage::disk('local')->exists($employee->photograph_path)) {
                    return;
                }
                foreach ($employee->credentials as $credential) {
                    if ($this->useAsProfile($employee, (string) $credential->content)) {
                        break;
                    }
                }
            });
    }

    private function put(Employee $employee, string $kind, string $slot, string $value): ?string
    {
        $content = StoredImage::normalize($value);
        if ($content === null) {
            return null;
        }

        EmployeeCredential::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'kind' => $kind, 'slot' => $slot],
            ['content' => $content],
        );

        return $content;
    }

    private function useAsProfile(Employee $employee, string $content): bool
    {
        $bytes = StoredImage::bytes($content);
        $mime = StoredImage::mimeOf($content);
        if ($bytes === null || $mime === null) {
            return false;
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $path = 'employee-photos/'.$employee->id.'/device-face.'.$extension;
        if ($employee->photograph_path && $employee->photograph_path !== $path && Storage::disk('local')->exists($employee->photograph_path)) {
            Storage::disk('local')->delete($employee->photograph_path);
        }
        Storage::disk('local')->put($path, $bytes);
        $employee->update(['photograph_path' => $path]);
        $employee->photograph_path = $path;

        return true;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, fn ($part) => $part !== ''));
        if ($parts === []) {
            return ['Device', 'User'];
        }
        if (count($parts) === 1) {
            return [$parts[0], 'User'];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    private function applyDeviceName(Employee $employee, string $name): void
    {
        $name = trim($name);
        if ($name === '' || $name === (string) $employee->employee_code || $name === $employee->full_name) {
            return;
        }
        if ($employee->updated_at && $employee->updated_at->greaterThan(now()->subMinutes(2))) {
            return;
        }
        [$first, $last] = $this->splitName($name);
        if ($last === 'User' && $employee->last_name !== 'User') {
            return;
        }
        $employee->update(['first_name' => $first, 'last_name' => $last]);
    }
}
