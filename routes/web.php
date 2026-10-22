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
use App\Http\Controllers\Operations\AdvanceController;
use App\Http\Controllers\Operations\AttendanceController;
use App\Http\Controllers\Operations\BankController;
use App\Http\Controllers\Operations\DeviceController;
use App\Http\Controllers\Operations\DeviceEventController;
use App\Http\Controllers\Operations\EzapiPushController;
use App\Http\Controllers\Operations\HolidayController;
use App\Http\Controllers\Operations\LeaveController;
use App\Http\Controllers\Operations\PayrollController;
use App\Http\Controllers\Operations\ReportController;
use App\Http\Controllers\Operations\SalaryController;
use App\Http\Controllers\Operations\ShiftController;
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
Route::get('device-events', [DeviceEventController::class, 'show'])->name('device-events.show');
Route::post('device-events', [DeviceEventController::class, 'store'])->middleware('throttle:120,1')->name('device-events.store');
Route::any('ezapi/push/{action}', [EzapiPushController::class, 'handle'])
    ->where('action', 'init|request|result|event|general')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
Route::get('two-factor/recovery', [TwoFactorController::class, 'recovery'])->middleware('auth')->name('two-factor.recovery');

Route::middleware(['auth', 'mfa'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/feed', [AttendanceController::class, 'feed'])->name('attendance.feed');
    Route::get('attendance/punches/{punch}/photo', [AttendanceController::class, 'punchPhoto'])->name('attendance.punch.photo');
    Route::get('attendance/credentials/{credential}/photo', [AttendanceController::class, 'credentialPhoto'])->name('attendance.credential.photo');
    Route::get('attendance/{employee}/feed', [AttendanceController::class, 'employeeFeed'])->name('attendance.employee.feed');
    Route::post('attendance/{employee}/day', [AttendanceController::class, 'updateDay'])->name('attendance.day.update');
    Route::delete('attendance/{employee}/day', [AttendanceController::class, 'destroyDay'])->name('attendance.day.destroy');
    Route::get('attendance/{employee}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('attendance/rule', [AttendanceController::class, 'updateRule'])->name('attendance.rule');
    Route::post('attendance/punch', [AttendanceController::class, 'punch'])->name('attendance.punch');
    Route::post('attendance/recalculate', [AttendanceController::class, 'recalculate'])->name('attendance.recalculate');

    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
    Route::post('shifts/roster', [ShiftController::class, 'roster'])->name('shifts.roster');
    Route::post('shifts/roster/bulk', [ShiftController::class, 'rosterBulk'])->name('shifts.roster.bulk');

    Route::get('leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::post('leave/types', [LeaveController::class, 'storeType'])->name('leave.types.store');
    Route::post('leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::put('leave/{leaveRequest}', [LeaveController::class, 'update'])->name('leave.update');
    Route::delete('leave/{leaveRequest}', [LeaveController::class, 'destroy'])->name('leave.destroy');
    Route::post('leave/{leaveRequest}/decide', [LeaveController::class, 'decide'])->name('leave.decide');

    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    Route::get('salary', [SalaryController::class, 'index'])->name('salary.index');
    Route::post('salary/heads', [SalaryController::class, 'storeHead'])->name('salary.heads.store');
    Route::post('salary/structures', [SalaryController::class, 'storeStructure'])->name('salary.structures.store');
    Route::post('salary/assign', [SalaryController::class, 'assign'])->name('salary.assign');

    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
    Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::post('payroll/{payroll}/compute', [PayrollController::class, 'compute'])->name('payroll.compute');
    Route::post('payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');

    Route::get('advances', [AdvanceController::class, 'index'])->name('advances.index');
    Route::post('advances', [AdvanceController::class, 'store'])->name('advances.store');
    Route::post('advances/{advance}/close', [AdvanceController::class, 'close'])->name('advances.close');

    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::post('devices/{device}/enroll', [DeviceController::class, 'enroll'])->name('devices.enroll');
    Route::put('device-terminal-users/{person}', [DeviceController::class, 'updateTerminalUser'])->name('devices.terminal.update');
    Route::delete('device-terminal-users/{person}', [DeviceController::class, 'destroyTerminalUser'])->name('devices.terminal.destroy');
    Route::post('device-enrollments/{enrollment}/block', [DeviceController::class, 'block'])->name('devices.block');
    Route::delete('device-enrollments/{enrollment}', [DeviceController::class, 'removeEnrollment'])->name('devices.enrollment.destroy');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('bank', [BankController::class, 'index'])->name('bank.index');
    Route::post('bank/accounts', [BankController::class, 'storeAccount'])->name('bank.accounts.store');
    Route::post('bank/batches', [BankController::class, 'storeBatch'])->name('bank.batches.store');
    Route::get('bank/batches/{batch}/export', [BankController::class, 'export'])->name('bank.export');

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
    Route::post('employees/{employee}/credentials', [EmployeeController::class, 'collectCredential'])->name('employees.credentials.collect');
    Route::get('employees/{employee}/credentials', [EmployeeController::class, 'credentialState'])->name('employees.credentials.state');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::get('employees/{employee}/documents/{document}/view', [EmployeeDocumentController::class, 'view'])->name('employees.documents.view');
    Route::get('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'download'])->name('employees.documents.download');
    Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy');
    Route::post('employees/{employee}/exit', [EmployeeExitController::class, 'store'])->name('employees.exit.store');
    Route::post('employees/{employee}/bank-accounts', [EmployeeBankAccountController::class, 'store'])->name('employees.bank-accounts.store');
    Route::patch('employees/{employee}/blocked', [EmployeeController::class, 'toggleBlocked'])->name('employees.blocked');
    Route::resource('employees', EmployeeController::class);

    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
