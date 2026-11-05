<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gate_passes', 'work_type')) {
            Schema::table('gate_passes', function (Blueprint $table) {
                $table->string('work_type', 16)->default('personal')->after('is_early_leave');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gate_passes', 'work_type')) {
            Schema::table('gate_passes', function (Blueprint $table) {
                $table->dropColumn('work_type');
            });
        }
    }
};
