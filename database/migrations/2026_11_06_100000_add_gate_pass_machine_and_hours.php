<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('gate_kind', 20)->default('attendance')->after('place');
        });

        Schema::table('gate_passes', function (Blueprint $table) {
            $table->dateTime('out_at')->nullable()->after('expires_at');
            $table->dateTime('in_at')->nullable()->after('out_at');
            $table->string('machine_status', 20)->default('none')->after('in_at');
            $table->string('machine_note', 191)->nullable()->after('machine_status');
        });

        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->foreignId('gate_pass_id')->nullable()->after('device_id')->constrained('gate_passes')->nullOnDelete();
        });

        Schema::table('attendance_days', function (Blueprint $table) {
            $table->unsignedInteger('minutes_away')->default(0)->after('minutes_worked');
            $table->unsignedInteger('minutes_personal')->default(0)->after('minutes_away');
            $table->unsignedInteger('minutes_office')->default(0)->after('minutes_personal');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropColumn(['minutes_away', 'minutes_personal', 'minutes_office']);
        });
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gate_pass_id');
        });
        Schema::table('gate_passes', function (Blueprint $table) {
            $table->dropColumn(['out_at', 'in_at', 'machine_status', 'machine_note']);
        });
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('gate_kind');
        });
    }
};
