<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });

        Schema::create('salary_sheet_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_salary', 12, 2)->default(0);
            $table->decimal('hours_per_day', 5, 2)->default(0);
            $table->decimal('hourly_rate', 12, 4)->default(0);
            $table->unsignedInteger('attended_minutes')->default(0);
            $table->unsignedInteger('paid_minutes')->default(0);
            $table->unsignedInteger('ot_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->decimal('absence_days', 5, 1)->default(0);
            $table->decimal('paid_leave_days', 5, 1)->default(0);
            $table->decimal('unpaid_leave_days', 5, 1)->default(0);
            $table->decimal('earned', 12, 2)->default(0);
            $table->decimal('advance', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->json('days');
            $table->json('breakdown');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_sheet_lines');
        Schema::dropIfExists('salary_sheets');
    }
};
