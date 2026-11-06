<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasReportingTree;
use App\Services\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use AuditsChanges, BelongsToCompany, HasReportingTree;

    protected $fillable = [
        'company_id',
        'department_id',
        'designation_id',
        'employee_type_id',
        'employment_status_id',
        'role_id',
        'reporting_manager_id',
        'user_id',
        'employee_code',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'date_of_birth',
        'gender',
        'address',
        'city',
        'state',
        'postal_code',
        'photograph_path',
        'joining_date',
        'confirmation_date',
        'probation_days',
        'notice_period_days',
        'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'confirmation_date' => 'date',
            'is_blocked' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }

    public function credentials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeCredential::class);
    }

    public function punches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workHour(): HasOne
    {
        return $this->hasOne(EmployeeWorkHour::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reporting_manager_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function needsReportingManager(): bool
    {
        $this->loadMissing(['role', 'user.roles']);

        if ($this->role?->requiresReportingManager()) {
            return true;
        }

        return (bool) $this->user?->roles->contains(
            fn (Role $role) => $role->requiresReportingManager()
        );
    }

    public function canBeReportingManager(): bool
    {
        $this->loadMissing(['role', 'user.roles']);

        if ($this->role?->appearsInManagerList()) {
            return true;
        }

        return (bool) $this->user?->roles->contains(
            fn (Role $role) => $role->appearsInManagerList()
        );
    }

    public function scopeCanManagePeople(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereHas(
                'role',
                fn (Builder $roles) => $roles->where('has_child', true)->where('is_active', true)
            )->orWhereHas(
                'user.roles',
                fn (Builder $roles) => $roles->where('has_child', true)->where('is_active', true)
            );
        });
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmploymentEvent::class);
    }

    public function exits(): HasMany
    {
        return $this->hasMany(EmployeeExit::class);
    }

    public function latestExit(): HasOne
    {
        return $this->hasOne(EmployeeExit::class)->latestOfMany();
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function activeBankAccount(): HasOne
    {
        return $this->hasOne(EmployeeBankAccount::class)->where('is_active', true);
    }

    public function customValues(): HasMany
    {
        return $this->hasMany(EmployeeCustomFieldValue::class);
    }

    public function gatePasses(): HasMany
    {
        return $this->hasMany(GatePass::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AccessScope::class)->applyToEmployees($query, $user);
    }
}
