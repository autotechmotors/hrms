<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->unsignedSmallInteger('half_day_minutes')->nullable();
            $table->unsignedSmallInteger('full_day_minutes')->nullable();
            $table->boolean('mark_absent_without_punch')->default(true);
            $table->timestamps();
            $table->unique('company_id');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->boolean('is_flexible')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('shift_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_off')->default(false);
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->time('punch_in')->nullable();
            $table->time('punch_out')->nullable();
            $table->string('status', 20);
            $table->unsignedSmallInteger('minutes_worked')->default(0);
            $table->string('source', 20)->default('manual');
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->boolean('is_paid')->default(true);
            $table->decimal('annual_days', 6, 1)->default(0);
            $table->boolean('counts_weekly_off')->default(false);
            $table->boolean('counts_holidays')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days', 6, 1);
            $table->string('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('kind', 20)->default('public');
            $table->timestamps();
            $table->unique(['company_id', 'holiday_date']);
        });

        Schema::create('salary_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->string('category', 20);
            $table->string('method', 20)->default('fixed');
            $table->decimal('value', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('salary_structure_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_head_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained()->restrictOnDelete();
            $table->decimal('gross_monthly', 12, 2);
            $table->date('effective_from');
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('status', 20)->default('draft');
            $table->unsignedTinyInteger('working_days')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'period_year', 'period_month']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('paid_days', 6, 1)->default(0);
            $table->decimal('lop_days', 6, 1)->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->decimal('emi', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->date('starts_on');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('serial')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('place')->nullable();
            $table->string('status', 20)->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('expires_on')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            $table->unique(['device_id', 'employee_id']);
        });

        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('bank_name');
            $table->text('account_number');
            $table->string('account_last4', 4);
            $table->string('ifsc', 20);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('payment_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_bank_account_id')->constrained()->restrictOnDelete();
            $table->date('scheduled_on');
            $table->string('status', 20)->default('draft');
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payment_batch_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('account_last4', 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_batch_lines');
        Schema::dropIfExists('payment_batches');
        Schema::dropIfExists('company_bank_accounts');
        Schema::dropIfExists('device_users');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('advances');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_structure_lines');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_heads');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('shift_rosters');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('attendance_rules');
    }
};
