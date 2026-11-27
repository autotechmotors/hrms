<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('block_inactive_days')->nullable()->after('is_blocked');
            $table->boolean('in_suspended')->default(false)->after('block_inactive_days');
            $table->boolean('out_door_closed')->default(false)->after('in_suspended');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['block_inactive_days', 'in_suspended', 'out_door_closed']);
        });
    }
};
