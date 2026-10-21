<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_terminal_users', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable()->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('device_terminal_users', function (Blueprint $table) {
            $table->dropColumn('dismissed_at');
        });
    }
};
