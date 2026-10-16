<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employees\EmployeeBankAccountController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\EmployeeDocumentController;
use App\Http\Controllers\Employees\EmployeeExitController;
use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Organization\BrandingController;
use App\Http\Controllers\Organization\CompanyController;
use App\Http\Controllers\Organization\CustomFieldController;
use App\Http\Controllers\Organization\DepartmentController;
use App\Http\Controllers\Organization\DesignationController;
use App\Http\Controllers\Organization\EmployeeTypeController;
use App\Http\Controllers\Organization\EmploymentStatusController;
use App\Http\Controllers\Organization\FinancialYearController;
use App\Http\Controllers\Organization\LocationController;
use App\Http\Controllers\Organization\WorkCalendarController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('two-factor/setup', [TwoFactorController::class, 'confirm'])->middleware('throttle:two-factor');
    Route::get('two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor/challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:two-factor');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('two-factor/recovery', [TwoFactorController::class, 'recovery'])->middleware('auth')->name('two-factor.recovery');

Route::middleware(['auth', 'mfa'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('attendance', [\App\Http\Controllers\Operations\AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance/rule', [\App\Http\Controllers\Operations\AttendanceController::class, 'updateRule'])->name('attendance.rule');
    Route::post('attendance/punch', [\App\Http\Controllers\Operations\AttendanceController::class, 'punch'])->name('attendance.punch');
    Route::post('attendance/recalculate', [\App\Http\Controllers\Operations\AttendanceController::class, 'recalculate'])->name('attendance.recalculate');

    Route::get('shifts', [\App\Http\Controllers\Operations\ShiftController::class, 'index'])->name('shifts.index');
    Route::post('shifts', [\App\Http\Controllers\Operations\ShiftController::class, 'store'])->name('shifts.store');
    Route::delete('shifts/{shift}', [\App\Http\Controllers\Operations\ShiftController::class, 'destroy'])->name('shifts.destroy');
    Route::post('shifts/roster', [\App\Http\Controllers\Operations\ShiftController::class, 'roster'])->name('shifts.roster');

    Route::get('leave', [\App\Http\Controllers\Operations\LeaveController::class, 'index'])->name('leave.index');
    Route::post('leave/types', [\App\Http\Controllers\Operations\LeaveController::class, 'storeType'])->name('leave.types.store');
    Route::post('leave', [\App\Http\Controllers\Operations\LeaveController::class, 'store'])->name('leave.store');
    Route::post('leave/{leaveRequest}/decide', [\App\Http\Controllers\Operations\LeaveController::class, 'decide'])->name('leave.decide');

    Route::get('holidays', [\App\Http\Controllers\Operations\HolidayController::class, 'index'])->name('holidays.index');
    Route::post('holidays', [\App\Http\Controllers\Operations\HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('holidays/{holiday}', [\App\Http\Controllers\Operations\HolidayController::class, 'destroy'])->name('holidays.destroy');

    Route::get('salary', [\App\Http\Controllers\Operations\SalaryController::class, 'index'])->name('salary.index');
    Route::post('salary/heads', [\App\Http\Controllers\Operations\SalaryController::class, 'storeHead'])->name('salary.heads.store');
    Route::post('salary/structures', [\App\Http\Controllers\Operations\SalaryController::class, 'storeStructure'])->name('salary.structures.store');
    Route::post('salary/assign', [\App\Http\Controllers\Operations\SalaryController::class, 'assign'])->name('salary.assign');

    Route::get('payroll', [\App\Http\Controllers\Operations\PayrollController::class, 'index'])->name('payroll.index');
    Route::post('payroll', [\App\Http\Controllers\Operations\PayrollController::class, 'store'])->name('payroll.store');
    Route::get('payroll/{payroll}', [\App\Http\Controllers\Operations\PayrollController::class, 'show'])->name('payroll.show');
    Route::post('payroll/{payroll}/compute', [\App\Http\Controllers\Operations\PayrollController::class, 'compute'])->name('payroll.compute');
    Route::post('payroll/{payroll}/approve', [\App\Http\Controllers\Operations\PayrollController::class, 'approve'])->name('payroll.approve');

    Route::get('advances', [\App\Http\Controllers\Operations\AdvanceController::class, 'index'])->name('advances.index');
    Route::post('advances', [\App\Http\Controllers\Operations\AdvanceController::class, 'store'])->name('advances.store');
    Route::post('advances/{advance}/close', [\App\Http\Controllers\Operations\AdvanceController::class, 'close'])->name('advances.close');

    Route::get('devices', [\App\Http\Controllers\Operations\DeviceController::class, 'index'])->name('devices.index');
    Route::post('devices', [\App\Http\Controllers\Operations\DeviceController::class, 'store'])->name('devices.store');
    Route::get('devices/{device}', [\App\Http\Controllers\Operations\DeviceController::class, 'show'])->name('devices.show');
    Route::put('devices/{device}', [\App\Http\Controllers\Operations\DeviceController::class, 'update'])->name('devices.update');
    Route::post('devices/{device}/enroll', [\App\Http\Controllers\Operations\DeviceController::class, 'enroll'])->name('devices.enroll');
    Route::post('device-enrollments/{enrollment}/block', [\App\Http\Controllers\Operations\DeviceController::class, 'block'])->name('devices.block');
    Route::delete('device-enrollments/{enrollment}', [\App\Http\Controllers\Operations\DeviceController::class, 'removeEnrollment'])->name('devices.enrollment.destroy');

    Route::get('reports', [\App\Http\Controllers\Operations\ReportController::class, 'index'])->name('reports.index');

    Route::get('bank', [\App\Http\Controllers\Operations\BankController::class, 'index'])->name('bank.index');
    Route::post('bank/accounts', [\App\Http\Controllers\Operations\BankController::class, 'storeAccount'])->name('bank.accounts.store');
    Route::post('bank/batches', [\App\Http\Controllers\Operations\BankController::class, 'storeBatch'])->name('bank.batches.store');
    Route::get('bank/batches/{batch}/export', [\App\Http\Controllers\Operations\BankController::class, 'export'])->name('bank.export');

    Route::resource('companies', CompanyController::class)->except(['show']);
    Route::resource('branches', BranchController::class)->except(['show']);
    Route::resource('locations', LocationController::class)->except(['show']);
    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::resource('designations', DesignationController::class)->except(['show']);
    Route::resource('employee-types', EmployeeTypeController::class)->except(['show']);
    Route::resource('employment-statuses', EmploymentStatusController::class)->except(['show']);
    Route::resource('financial-years', FinancialYearController::class)->parameters(['financial-years' => 'financialYear'])->except(['show']);
    Route::resource('work-calendars', WorkCalendarController::class)->parameters(['work-calendars' => 'workCalendar'])->except(['show']);
    Route::resource('custom-fields', CustomFieldController::class)->parameters(['custom-fields' => 'customField'])->except(['show']);

    Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::put('branding/{company}', [BrandingController::class, 'update'])->name('branding.update');

    Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::get('employees/import', [EmployeeController::class, 'importForm'])->name('employees.import');
    Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import.store');
    Route::get('employees/import/template', [EmployeeController::class, 'template'])->name('employees.import.template');
    Route::get('employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::get('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'download'])->name('employees.documents.download');
    Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy');
    Route::post('employees/{employee}/exit', [EmployeeExitController::class, 'store'])->name('employees.exit.store');
    Route::post('employees/{employee}/bank-accounts', [EmployeeBankAccountController::class, 'store'])->name('employees.bank-accounts.store');
    Route::resource('employees', EmployeeController::class);

    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
