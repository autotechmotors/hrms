<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->unsignedBigInteger('salary_structure_id')->nullable()->change();
        });

        Schema::table('payment_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('payroll_run_id')->nullable()->change();
            $table->foreignId('salary_sheet_id')->nullable()->constrained('salary_sheets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salary_sheet_id');
        });
    }
};
