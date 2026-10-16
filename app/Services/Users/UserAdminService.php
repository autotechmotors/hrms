<?php

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAdminService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $roleIds
     * @param  list<array{scope_type: string, scope_id: int}>  $scopes
     */
    public function save(?User $user, array $data, array $roleIds, array $scopes, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $roleIds, $scopes, $actor): User {
            $roles = Role::query()->whereIn('id', $roleIds)->get();

            if ($roles->isEmpty()) {
                throw ValidationException::withMessages(['roles' => 'Select at least one staff role. Employees do not get a login.']);
            }

            if ($roles->contains(fn (Role $role) => $role->slug === 'employee')) {
                throw ValidationException::withMessages(['roles' => 'Employees do not get a login. Assign a staff role such as HR, Manager, or Director.']);
            }

            if ($roles->contains(fn (Role $role) => $role->slug === 'super-admin') && ! $actor->isSuperAdmin()) {
                throw ValidationException::withMessages(['roles' => 'Only a Super Admin can assign the Super Admin role.']);
            }

            if ($user && $user->isSuperAdmin() && ! $roles->contains(fn (Role $role) => $role->slug === 'super-admin')) {
                $remaining = User::query()
                    ->whereKeyNot($user->id)
                    ->where('is_active', true)
                    ->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))
                    ->exists();

                if (! $remaining) {
                    throw ValidationException::withMessages(['roles' => 'The last active Super Admin must keep that role.']);
                }
            }

            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => (bool) ($data['is_active'] ?? false),
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            } elseif ($user === null) {
                throw ValidationException::withMessages(['password' => 'A password is required for a new user.']);
            }

            if ($user) {
                $user->update($payload);
            } else {
                $user = User::query()->create($payload);
            }

            $user->roles()->sync($roles->pluck('id'));
            $user->unsetRelation('roles');

            $user->scopes()->delete();
            foreach ($scopes as $scope) {
                if (blank($scope['scope_type'] ?? null) || blank($scope['scope_id'] ?? null)) {
                    continue;
                }
                UserScope::query()->create([
                    'user_id' => $user->id,
                    'scope_type' => $scope['scope_type'],
                    'scope_id' => (int) $scope['scope_id'],
                ]);
            }

            return $user->refresh();
        });
    }

    public function deactivate(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => 'You cannot deactivate your own account.']);
        }

        if ($user->isSuperAdmin()) {
            $remaining = User::query()
                ->whereKeyNot($user->id)
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))
                ->exists();

            if (! $remaining) {
                throw ValidationException::withMessages(['user' => 'The last active Super Admin cannot be deactivated.']);
            }
        }

        $user->update(['is_active' => false]);
    }
}
