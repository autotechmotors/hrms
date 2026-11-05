<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id', 'department_id']);
            $table->dropColumn(['location_id', 'branch_id']);
        });

        Schema::table('employment_events', function (Blueprint $table) {
            $table->dropForeign(['from_branch_id']);
            $table->dropForeign(['to_branch_id']);
            $table->dropColumn(['from_branch_id', 'to_branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->index(['branch_id', 'department_id']);
        });

        Schema::table('employment_events', function (Blueprint $table) {
            $table->foreignId('from_branch_id')->nullable()->after('reason')->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->after('from_branch_id')->constrained('branches')->nullOnDelete();
        });
    }
};
