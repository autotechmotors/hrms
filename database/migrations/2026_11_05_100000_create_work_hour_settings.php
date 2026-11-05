<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_work_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours_per_day', 4, 2);
            $table->timestamps();
            $table->unique('department_id');
        });

        Schema::create('employee_work_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->boolean('uses_department')->default(true);
            $table->decimal('hours_per_day', 4, 2)->nullable();
            $table->timestamps();
            $table->unique('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_hours');
        Schema::dropIfExists('department_work_hours');
    }
};
