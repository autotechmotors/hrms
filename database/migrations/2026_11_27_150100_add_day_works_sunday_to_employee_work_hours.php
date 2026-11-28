<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->boolean('day_works_sunday')->nullable()->after('sunday_end');
        });

        foreach (DB::table('employee_work_hours')->where('shift_kind', 'day')->whereNotNull('works_sunday')->get(['id', 'works_sunday']) as $row) {
            DB::table('employee_work_hours')->where('id', $row->id)->update([
                'day_works_sunday' => (bool) $row->works_sunday,
                'works_sunday' => null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->dropColumn('day_works_sunday');
        });
    }
};
