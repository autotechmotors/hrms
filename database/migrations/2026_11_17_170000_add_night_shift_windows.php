<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_work_hours', function (Blueprint $table) {
            $table->time('night_weekday_start')->default('21:00:00');
            $table->time('night_weekday_end')->default('06:00:00');
            $table->time('night_sunday_start')->default('21:00:00');
            $table->time('night_sunday_end')->default('02:00:00');
        });

        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->string('shift_kind', 10)->default('day');
            $table->time('night_weekday_start')->nullable();
            $table->time('night_weekday_end')->nullable();
            $table->time('night_sunday_start')->nullable();
            $table->time('night_sunday_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->dropColumn([
                'shift_kind',
                'night_weekday_start',
                'night_weekday_end',
                'night_sunday_start',
                'night_sunday_end',
            ]);
        });

        Schema::table('department_work_hours', function (Blueprint $table) {
            $table->dropColumn([
                'night_weekday_start',
                'night_weekday_end',
                'night_sunday_start',
                'night_sunday_end',
            ]);
        });
    }
};
