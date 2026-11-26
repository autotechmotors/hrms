<?php

namespace App\Services\Employees;

use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\Devices\EmployeePush;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployeeRemover
{
    public function __construct(private readonly EmployeePush $push) {}

    /**
     * Remove the person and every row tied to them across the system.
     * Device faces are dropped after the database commit.
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function delete(Employee $employee): void
    {
        $employee->loadMissing(['documents', 'user.roles']);

        if ($employee->user?->isSuperAdmin()) {
            $remaining = User::query()
                ->whereKeyNot($employee->user->id)
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))
                ->exists();
            if (! $remaining) {
                throw ValidationException::withMessages([
                    'employee' => 'The last active Super Admin cannot be deleted.',
                ]);
            }
        }

        $linked = $this->push->linked($employee);
        $userId = trim((string) $employee->employee_code);
        $login = $employee->user;

        DB::transaction(function () use ($employee, $linked, $userId, $login): void {
            $kept = [];
            foreach ($linked as $device) {
                $row = DeviceTerminalUser::query()->updateOrCreate(
                    ['device_id' => $device->id, 'user_id' => $userId],
                    ['name' => $employee->full_name, 'employee_id' => $employee->id],
                );
                $kept[] = $row->id;
            }

            foreach ($employee->documents as $document) {
                $this->forgetFile($document->file_path);
            }
            $this->forgetFile($employee->photograph_path);

            EmployeeDocument::query()->where('employee_id', $employee->id)->delete();

            $employee->delete();

            if ($kept !== []) {
                DeviceTerminalUser::query()->whereIn('id', $kept)->update([
                    'employee_id' => null,
                    'dismissed_at' => now(),
                ]);
            }

            if ($login) {
                $login->roles()->detach();
                $login->scopes()->delete();
                $login->notifications()->delete();
                $login->delete();
            }
        });

        $this->push->drop($linked, $userId);
    }

    private function forgetFile(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
