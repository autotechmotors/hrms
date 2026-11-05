<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('has_parent')->default(false)->after('requires_mfa');
            $table->boolean('has_child')->default(false)->after('has_parent');
            $table->boolean('is_active')->default(true)->after('has_child');
        });

        DB::table('roles')->whereIn('slug', ['manager', 'director'])->update([
            'has_parent' => true,
            'has_child' => true,
            'is_active' => true,
        ]);

        DB::table('roles')->whereIn('slug', ['super-admin', 'hr-admin'])->update([
            'has_parent' => false,
            'has_child' => true,
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['has_parent', 'has_child', 'is_active']);
        });
    }
};
