<div class="logo"><i>{{ strtoupper(substr(config('app.name'), 0, 1)) }}</i><span class="lb">{{ config('app.name') }}</span></div>
<nav class="nv" id="nv">
    <button class="gh" type="button"><span>Overview</span><x-i name="down" :size="12" /></button>
    <div class="ng">
        <a class="ni {{ request()->routeIs('dashboard') ? 'on' : '' }}" href="{{ route('dashboard') }}" data-page="dashboard"><x-i name="dash" :size="17" /><span class="lb">Dashboard</span></a>
    </div>

    <button class="gh" type="button"><span>People</span><x-i name="down" :size="12" /></button>
    <div class="ng">
        @permission('employees.view')
            <a class="ni {{ request()->routeIs('employees.*') ? 'on' : '' }}" href="{{ route('employees.index') }}" data-page="employees"><x-i name="users" :size="17" /><span class="lb">Employees</span></a>
            <a class="ni {{ request()->routeIs('attendance.*') ? 'on' : '' }}" href="{{ route('attendance.index') }}" data-page="attendance"><x-i name="clock" :size="17" /><span class="lb">Attendance</span></a>
            <a class="ni {{ request()->routeIs('shifts.*') ? 'on' : '' }}" href="{{ route('shifts.index') }}" data-page="shifts & rosters"><x-i name="cal" :size="17" /><span class="lb">Shifts & Rosters</span></a>
            <a class="ni {{ request()->routeIs('leave.*') ? 'on' : '' }}" href="{{ route('leave.index') }}" data-page="leave management"><x-i name="minus" :size="17" /><span class="lb">Leave Management</span></a>
            <a class="ni {{ request()->routeIs('holidays.*') ? 'on' : '' }}" href="{{ route('holidays.index') }}" data-page="holidays"><x-i name="sun" :size="17" /><span class="lb">Holidays</span></a>
        @endpermission
    </div>

    <button class="gh" type="button"><span>Payroll</span><x-i name="down" :size="12" /></button>
    <div class="ng">
        @permission('employees.view')
            <a class="ni {{ request()->routeIs('payroll.*') ? 'on' : '' }}" href="{{ route('payroll.index') }}" data-page="payroll"><x-i name="wallet" :size="17" /><span class="lb">Payroll</span></a>
            <a class="ni {{ request()->routeIs('salary.*') ? 'on' : '' }}" href="{{ route('salary.index') }}" data-page="salary structures"><x-i name="layers" :size="17" /><span class="lb">Salary Structures</span></a>
            <a class="ni {{ request()->routeIs('advances.*') ? 'on' : '' }}" href="{{ route('advances.index') }}" data-page="advances & deductions"><x-i name="minus" :size="17" /><span class="lb">Advances & Deductions</span></a>
        @endpermission
    </div>

    <button class="gh" type="button"><span>Operations</span><x-i name="down" :size="12" /></button>
    <div class="ng">
        @permission('employees.view')
            <a class="ni {{ request()->routeIs('devices.*') ? 'on' : '' }}" href="{{ route('devices.index') }}" data-page="dahua devices"><x-i name="cpu" :size="17" /><span class="lb">Dahua Devices</span></a>
            <a class="ni {{ request()->routeIs('reports.*') ? 'on' : '' }}" href="{{ route('reports.index') }}" data-page="reports & analytics"><x-i name="chart" :size="17" /><span class="lb">Reports & Analytics</span></a>
            <a class="ni {{ request()->routeIs('bank.*') ? 'on' : '' }}" href="{{ route('bank.index') }}" data-page="bank transfers"><x-i name="bank" :size="17" /><span class="lb">Bank Transfers</span></a>
        @endpermission
    </div>

    <button class="gh" type="button"><span>Administration</span><x-i name="down" :size="12" /></button>
    <div class="ng">
        @permission('users.view')
            <a class="ni {{ request()->routeIs('users.*') ? 'on' : '' }}" href="{{ route('users.index') }}" data-page="user management"><x-i name="users" :size="17" /><span class="lb">User Management</span></a>
        @endpermission
        @permission('roles.view')
            <a class="ni {{ request()->routeIs('roles.*') ? 'on' : '' }}" href="{{ route('roles.index') }}" data-page="roles & permissions"><x-i name="shield" :size="17" /><span class="lb">Roles & Permissions</span></a>
        @endpermission
        @permission('departments.view')
            <a class="ni {{ request()->routeIs('departments.*') ? 'on' : '' }}" href="{{ route('departments.index') }}" data-page="departments"><x-i name="building" :size="17" /><span class="lb">Departments</span></a>
        @endpermission
        @permission('locations.view')
            <a class="ni {{ request()->routeIs('locations.*') ? 'on' : '' }}" href="{{ route('locations.index') }}" data-page="locations"><x-i name="building" :size="17" /><span class="lb">Locations</span></a>
        @endpermission
        @permission('designations.view')
            <a class="ni {{ request()->routeIs('designations.*') || request()->routeIs('employee-types.*') ? 'on' : '' }}" href="{{ route('designations.index') }}" data-page="designations"><x-i name="users" :size="17" /><span class="lb">Designations</span></a>
        @endpermission
        @permission('employment_statuses.view')
            <a class="ni {{ request()->routeIs('employment-statuses.*') ? 'on' : '' }}" href="{{ route('employment-statuses.index') }}" data-page="statuses"><x-i name="set" :size="17" /><span class="lb">Statuses</span></a>
        @endpermission
        @permission('financial_years.view')
            <a class="ni {{ request()->routeIs('financial-years.*') ? 'on' : '' }}" href="{{ route('financial-years.index') }}" data-page="financial years"><x-i name="cal" :size="17" /><span class="lb">Financial years</span></a>
        @endpermission
        @permission('work_calendars.view')
            <a class="ni {{ request()->routeIs('work-calendars.*') ? 'on' : '' }}" href="{{ route('work-calendars.index') }}" data-page="work calendars"><x-i name="cal" :size="17" /><span class="lb">Work calendars</span></a>
        @endpermission
        @permission('custom_fields.view')
            <a class="ni {{ request()->routeIs('custom-fields.*') ? 'on' : '' }}" href="{{ route('custom-fields.index') }}" data-page="custom fields"><x-i name="set" :size="17" /><span class="lb">Custom fields</span></a>
        @endpermission
        @permission('branding.edit')
            <a class="ni {{ request()->routeIs('branding.*') ? 'on' : '' }}" href="{{ route('branding.edit') }}" data-page="branding"><x-i name="set" :size="17" /><span class="lb">Branding</span></a>
        @endpermission
        @permission('audit_logs.view')
            <a class="ni {{ request()->routeIs('audit-logs.*') ? 'on' : '' }}" href="{{ route('audit-logs.index') }}" data-page="audit logs"><x-i name="log" :size="17" /><span class="lb">Audit Logs</span></a>
        @endpermission
    </div>
</nav>
