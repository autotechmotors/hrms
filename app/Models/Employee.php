<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use App\Services\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'branch_id',
        'location_id',
        'department_id',
        'designation_id',
        'employee_type_id',
        'employment_status_id',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AccessScope::class)->applyToEmployees($query, $user);
    }
}
