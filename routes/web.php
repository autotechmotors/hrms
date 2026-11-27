<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employees\EmployeeBankAccountController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\EmployeeDocumentController;
use App\Http\Controllers\Employees\EmployeeExitController;
use App\Http\Controllers\Employees\EmployeeShiftController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Operations\AdvanceController;
use App\Http\Controllers\Operations\AttendanceController;
use App\Http\Controllers\Operations\BankController;
use App\Http\Controllers\Operations\DeviceController;
use App\Http\Controllers\Operations\DeviceEventController;
use App\Http\Controllers\Operations\GatePassController;
use App\Http\Controllers\Operations\HikvisionEventController;
use App\Http\Controllers\Operations\HolidayController;
use App\Http\Controllers\Operations\HoursPayController;
use App\Http\Controllers\Operations\LeaveController;
use App\Http\Controllers\Operations\ReportController;
use App\Http\Controllers\Operations\SalarySheetController;
use App\Http\Controllers\Operations\ShiftController;
// use App\Http\Controllers\Organization\BrandingController;
use App\Http\Controllers\Organization\CompanyController;
use App\Http\Controllers\Organization\CustomFieldController;
use App\Http\Controllers\Organization\DepartmentController;
use App\Http\Controllers\Organization\DesignationController;
use App\Http\Controllers\Organization\EmployeeTypeController;
use App\Http\Controllers\Organization\EmploymentStatusController;
// use App\Http\Controllers\Organization\FinancialYearController;
use App\Http\Controllers\Organization\WorkCalendarController;
use Illuminate\Support\Facades\Route;

Route::get('locale/{locale}', LocaleController::class)->whereIn('locale', ['en', 'hi', 'gu'])->name('locale.update');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password-email')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-email')->name('password.update');
    Route::get('two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('two-factor/setup', [TwoFactorController::class, 'confirm'])->middleware('throttle:two-factor');
    Route::get('two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor/challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:two-factor');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('device-events', [DeviceEventController::class, 'show'])->middleware('device.log')->name('device-events.show');
Route::post('device-events', [DeviceEventController::class, 'store'])->middleware(['device.log', 'throttle:120,1'])->name('device-events.store');
Route::get('hikvision-events/{token}/jobs/next', [HikvisionEventController::class, 'nextJob'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:120,1')
    ->name('hikvision-events.jobs.next');
Route::post('hikvision-events/{token}/jobs/{id}', [HikvisionEventController::class, 'finishJob'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:120,1')
    ->name('hikvision-events.jobs.finish');
Route::post('hikvision-events/{token}/people', [HikvisionEventController::class, 'people'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:10,1')
    ->name('hikvision-events.people');
Route::match(['get', 'post'], 'hikvision-events/{token}', HikvisionEventController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:120,1')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->name('hikvision-events.store');
Route::get('two-factor/recovery', [TwoFactorController::class, 'recovery'])->middleware('auth')->name('two-factor.recovery');

Route::middleware(['auth', 'mfa'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('more', [AccountController::class, 'more'])->name('more');
    Route::get('profile', [AccountController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [AccountController::class, 'update'])->name('profile.update');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

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
    Route::put('leave/types/{leaveType}', [LeaveController::class, 'updateType'])->name('leave.types.update');
    Route::delete('leave/types/{leaveType}', [LeaveController::class, 'destroyType'])->name('leave.types.destroy');
    Route::post('leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::put('leave/{leaveRequest}', [LeaveController::class, 'update'])->name('leave.update');
    Route::delete('leave/{leaveRequest}', [LeaveController::class, 'destroy'])->name('leave.destroy');
    Route::post('leave/{leaveRequest}/decide', [LeaveController::class, 'decide'])->name('leave.decide');

    Route::prefix('gate-passes')->name('gate-passes.')->group(function (): void {
        Route::get('/', [GatePassController::class, 'index'])->name('index');
        Route::get('create', [GatePassController::class, 'create'])->name('create');
        Route::post('/', [GatePassController::class, 'store'])->name('store');
        Route::get('settings', [GatePassController::class, 'settings'])->name('settings');
        Route::put('settings', [GatePassController::class, 'updateSettings'])->name('settings.update');
        Route::get('{gatePass}', [GatePassController::class, 'show'])->name('show');
        Route::put('{gatePass}', [GatePassController::class, 'update'])->name('update');
        Route::delete('{gatePass}', [GatePassController::class, 'destroy'])->name('destroy');
        Route::post('{gatePass}/approve', [GatePassController::class, 'approve'])->name('approve');
        Route::post('{gatePass}/reject', [GatePassController::class, 'reject'])->name('reject');
    });

    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
    Route::post('employee-holidays', [HolidayController::class, 'storeEmployee'])->name('employee-holidays.store');
    Route::delete('employee-holidays/{employeeHoliday}', [HolidayController::class, 'destroyEmployee'])->name('employee-holidays.destroy');

    Route::get('hours-pay', [HoursPayController::class, 'index'])->name('hours-pay.index');
    Route::post('hours-pay/departments/{department}', [HoursPayController::class, 'storeDepartment'])->name('hours-pay.departments.store');
    Route::get('hours-pay/employees/{employee}', [HoursPayController::class, 'show'])->name('hours-pay.show');
    Route::post('hours-pay/employees/{employee}/hours', [HoursPayController::class, 'storeEmployeeHours'])->name('hours-pay.employees.hours');
    Route::post('hours-pay/employees/{employee}/salary', [HoursPayController::class, 'storeEmployeeSalary'])->name('hours-pay.employees.salary');

    Route::get('salary-sheets', [SalarySheetController::class, 'index'])->name('salary-sheets.index');
    Route::get('salary-sheets/people', [SalarySheetController::class, 'people'])->name('salary-sheets.people');
    Route::post('salary-sheets', [SalarySheetController::class, 'store'])->name('salary-sheets.store');
    Route::get('salary-sheets/{salarySheet}', [SalarySheetController::class, 'show'])->name('salary-sheets.show');
    Route::post('salary-sheets/{salarySheet}/approve', [SalarySheetController::class, 'approve'])->name('salary-sheets.approve');
    Route::post('salary-sheets/{salarySheet}/reject', [SalarySheetController::class, 'reject'])->name('salary-sheets.reject');
    Route::get('salary-sheets/{salarySheet}/excel', [SalarySheetController::class, 'excel'])->name('salary-sheets.excel');
    Route::get('salary-sheets/{salarySheet}/pdf', [SalarySheetController::class, 'pdf'])->name('salary-sheets.pdf');
    Route::get('salary-sheets/{salarySheet}/lines/{line}/excel', [SalarySheetController::class, 'lineExcel'])->name('salary-sheets.line.excel');
    Route::get('salary-sheets/{salarySheet}/lines/{line}/pdf', [SalarySheetController::class, 'linePdf'])->name('salary-sheets.line.pdf');
    Route::post('salary-sheets/{salarySheet}/lines/{line}/net', [SalarySheetController::class, 'adjustNet'])->name('salary-sheets.line.net');

    Route::get('advances', [AdvanceController::class, 'index'])->name('advances.index');
    Route::post('advances', [AdvanceController::class, 'store'])->name('advances.store');
    Route::put('advances/{advance}', [AdvanceController::class, 'update'])->name('advances.update');
    Route::delete('advances/{advance}', [AdvanceController::class, 'destroy'])->name('advances.destroy');
    Route::post('advances/{advance}/close', [AdvanceController::class, 'close'])->name('advances.close');
    Route::post('advances/{advance}/approve', [AdvanceController::class, 'approve'])->name('advances.approve');
    Route::post('advances/{advance}/reject', [AdvanceController::class, 'reject'])->name('advances.reject');

    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::post('devices/local-pc/enable', [DeviceController::class, 'enableLocalPc'])->name('devices.local-pc.enable');
    Route::post('devices/local-pc/disable', [DeviceController::class, 'disableLocalPc'])->name('devices.local-pc.disable');
    Route::post('devices/local-pc/start', [DeviceController::class, 'startLocalPc'])->name('devices.local-pc.start');
    Route::post('devices/local-pc/stop', [DeviceController::class, 'stopLocalPc'])->name('devices.local-pc.stop');
    Route::post('devices/{device}/sync', [DeviceController::class, 'sync'])->name('devices.sync');
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
    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::resource('designations', DesignationController::class)->except(['show']);
    Route::resource('employee-types', EmployeeTypeController::class)->except(['show']);
    Route::resource('employment-statuses', EmploymentStatusController::class)->except(['show']);
    // Route::resource('financial-years', FinancialYearController::class)->parameters(['financial-years' => 'financialYear'])->except(['show']);
    Route::resource('work-calendars', WorkCalendarController::class)->parameters(['work-calendars' => 'workCalendar'])->except(['show']);
    Route::resource('custom-fields', CustomFieldController::class)->parameters(['custom-fields' => 'customField'])->except(['show']);

    // Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
    // Route::put('branding/{company}', [BrandingController::class, 'update'])->name('branding.update');

    Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::get('employees/import', [EmployeeController::class, 'importForm'])->name('employees.import');
    Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import.store');
    Route::get('employees/import/template', [EmployeeController::class, 'template'])->name('employees.import.template');
    Route::get('employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
    Route::post('employees/{employee}/credentials', [EmployeeController::class, 'collectCredential'])->name('employees.credentials.collect');
    Route::delete('employees/{employee}/credentials/{credential}', [EmployeeController::class, 'destroyCredential'])->name('employees.credentials.destroy');
    Route::get('employees/{employee}/credentials', [EmployeeController::class, 'credentialState'])->name('employees.credentials.state');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::get('employees/{employee}/documents/{document}/view', [EmployeeDocumentController::class, 'view'])->name('employees.documents.view');
    Route::get('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'download'])->name('employees.documents.download');
    Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy');
    Route::post('employees/{employee}/exit', [EmployeeExitController::class, 'store'])->name('employees.exit.store');
    Route::post('employees/{employee}/bank-accounts', [EmployeeBankAccountController::class, 'store'])->name('employees.bank-accounts.store');
    Route::patch('employees/{employee}/blocked', [EmployeeController::class, 'toggleBlocked'])->name('employees.blocked');
    Route::patch('employees/{employee}/suspended', [EmployeeController::class, 'toggleSuspended'])->name('employees.suspended');
    Route::patch('employees/{employee}/out-door', [EmployeeController::class, 'toggleOutDoor'])->name('employees.out-door');
    Route::post('employees/{employee}/times', [EmployeeController::class, 'updateTimes'])->name('employees.times');
    Route::get('employees/{employee}/shifts', [EmployeeShiftController::class, 'show'])->name('employees.shifts.show');
    Route::post('employees/{employee}/shifts', [EmployeeShiftController::class, 'update'])->name('employees.shifts.update');
    Route::resource('employees', EmployeeController::class);

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    Route::redirect('users', '/employees', 301);
    Route::redirect('users/{any}', '/employees', 301)->where('any', '.*');
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

Route::fallback(function () {
    abort(404);
});
