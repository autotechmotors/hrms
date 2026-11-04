<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_rules', function (Blueprint $table) {
            $table->boolean('store_selfie')->default(true)->after('mark_absent_without_punch');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_rules', function (Blueprint $table) {
            $table->dropColumn('store_selfie');
        });
    }
};
