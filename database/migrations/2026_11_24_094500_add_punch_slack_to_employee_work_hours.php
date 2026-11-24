<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->unsignedSmallInteger('punch_slack_minutes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('employee_work_hours', function (Blueprint $table) {
            $table->dropColumn('punch_slack_minutes');
        });
    }
};
