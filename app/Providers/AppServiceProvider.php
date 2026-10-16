<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\FinancialYear;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkCalendar;
use App\Services\Workspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Password::defaults(function () {
            return Password::min(8)->mixedCase()->numbers();
        });

        View::composer('*', function ($view): void {
            if (! auth()->check()) {
                return;
            }

            $workspace = app(Workspace::class);
            $view->with('workspaceCompany', $workspace->company(auth()->user()));
            $view->with('workspaceBranch', $workspace->branch(auth()->user()));
        });

        Blade::if('permission', function (string $permission): bool {
            return auth()->check() && auth()->user()->hasPermission($permission);
        });

        Gate::before(function ($user) {
            return $user instanceof User && $user->isSuperAdmin() ? true : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->session()->get('pending_login_id').'|'.$request->ip());
        });

        $this->bindVisible('company', Company::class);
        $this->bindVisible('branch', Branch::class);
        $this->bindVisible('location', Location::class);
        $this->bindVisible('department', Department::class);
        $this->bindVisible('designation', Designation::class);
        $this->bindVisible('employee_type', EmployeeType::class);
        $this->bindVisible('employment_status', EmploymentStatus::class);
        $this->bindVisible('financialYear', FinancialYear::class);
        $this->bindVisible('workCalendar', WorkCalendar::class);
        $this->bindVisible('customField', EmployeeCustomField::class);
        $this->bindVisible('employee', Employee::class);
        $this->bindVisible('user', User::class);
    }

    private function bindVisible(string $key, string $model): void
    {
        Route::bind($key, function (string $value) use ($model) {
            abort_unless(auth()->check(), 401);
            $record = $model::query()->visibleTo(auth()->user())->whereKey($value)->first();
            abort_unless($record, 404);

            return $record;
        });
    }
}
