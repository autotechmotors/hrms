<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_work_hours', function (Blueprint $table) {
            $table->boolean('works_sunday')->default(true)->after('sunday_end');
        });
    }

    public function down(): void
    {
        Schema::table('department_work_hours', function (Blueprint $table) {
            $table->dropColumn('works_sunday');
        });
    }
};
