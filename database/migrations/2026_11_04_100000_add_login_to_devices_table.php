<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->after('ip_address');
            $table->text('password')->nullable()->after('username');
            $table->unsignedSmallInteger('port')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['username', 'password', 'port']);
        });
    }
};
