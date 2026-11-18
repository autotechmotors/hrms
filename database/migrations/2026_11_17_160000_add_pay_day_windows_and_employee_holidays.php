<?php

use App\Services\Operations\WorkWindowDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_work_hours', function (Blueprint $table) {
            $table->time('weekday_start')->default('09:00:00');
            $table->time('weekday_end')->default('18:00:00');
            $table->time('sunday_start')->default('08:00:00');
            $table->time('sunday_end')->default('13:00:00');
        });

        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->time('weekday_start')->nullable();
            $table->time('weekday_end')->nullable();
            $table->time('sunday_start')->nullable();
            $table->time('sunday_end')->nullable();
        });

        Schema::create('employee_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['employee_id', 'holiday_date']);
        });

        WorkWindowDefaults::apply();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_holidays');

        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->dropColumn(['weekday_start', 'weekday_end', 'sunday_start', 'sunday_end']);
        });

        Schema::table('department_work_hours', function (Blueprint $table) {
            $table->dropColumn(['weekday_start', 'weekday_end', 'sunday_start', 'sunday_end']);
        });
    }
};
