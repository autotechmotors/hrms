<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Database\Eloquent\Model;

trait ChecksModulePermission
{
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->module().'.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->hasPermission($this->module().'.view') && $this->inCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission($this->module().'.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasPermission($this->module().'.edit') && $this->inCompany($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermission($this->module().'.delete') && $this->inCompany($user, $model);
    }

    protected function inCompany(User $user, Model $model): bool
    {
        $companyId = $model->getAttribute('company_id') ?? $model->getKey();

        return app(AccessScope::class)->canAccessCompany($user, (int) $companyId);
    }
}
