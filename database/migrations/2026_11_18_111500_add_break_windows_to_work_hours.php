<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_work_hours', function (Blueprint $table) {
            $table->time('weekday_break_start')->nullable();
            $table->time('weekday_break_end')->nullable();
            $table->time('sunday_break_start')->nullable();
            $table->time('sunday_break_end')->nullable();
            $table->time('night_weekday_break_start')->nullable();
            $table->time('night_weekday_break_end')->nullable();
            $table->time('night_sunday_break_start')->nullable();
            $table->time('night_sunday_break_end')->nullable();
        });

        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->time('weekday_break_start')->nullable();
            $table->time('weekday_break_end')->nullable();
            $table->time('sunday_break_start')->nullable();
            $table->time('sunday_break_end')->nullable();
            $table->time('night_weekday_break_start')->nullable();
            $table->time('night_weekday_break_end')->nullable();
            $table->time('night_sunday_break_start')->nullable();
            $table->time('night_sunday_break_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('department_work_hours', function (Blueprint $table) {
            $table->dropColumn([
                'weekday_break_start',
                'weekday_break_end',
                'sunday_break_start',
                'sunday_break_end',
                'night_weekday_break_start',
                'night_weekday_break_end',
                'night_sunday_break_start',
                'night_sunday_break_end',
            ]);
        });

        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->dropColumn([
                'weekday_break_start',
                'weekday_break_end',
                'sunday_break_start',
                'sunday_break_end',
                'night_weekday_break_start',
                'night_weekday_break_end',
                'night_sunday_break_start',
                'night_sunday_break_end',
            ]);
        });
    }
};
