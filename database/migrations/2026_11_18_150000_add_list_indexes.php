<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index(['company_id', 'department_id'], 'employees_company_department_index');
            $table->index(['company_id', 'employment_status_id'], 'employees_company_status_index');
        });

        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->index(['employee_id', 'effective_from'], 'employee_salaries_employee_effective_index');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_company_department_index');
            $table->dropIndex('employees_company_status_index');
        });

        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropIndex('employee_salaries_employee_effective_index');
        });
    }
};
