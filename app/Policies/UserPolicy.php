<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view')
            && User::query()->visibleTo($user)->whereKey($target->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.edit')
            && User::query()->visibleTo($user)->whereKey($target->id)->exists();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermission('users.delete')
            && User::query()->visibleTo($user)->whereKey($target->id)->exists();
    }
}
