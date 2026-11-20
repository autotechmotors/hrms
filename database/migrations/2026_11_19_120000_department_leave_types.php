<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leave_types', 'department_id')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        $names = collect(Schema::getIndexes('leave_types'))->pluck('name');

        if (! $names->contains('leave_types_company_id_index')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->index('company_id', 'leave_types_company_id_index');
            });
        }

        if ($names->contains('leave_types_company_id_code_unique')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropUnique('leave_types_company_id_code_unique');
            });
        }

        $names = collect(Schema::getIndexes('leave_types'))->pluck('name');
        if (! $names->contains('leave_types_company_id_department_id_code_unique')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->unique(['company_id', 'department_id', 'code']);
            });
        }

        if (! Schema::hasColumn('leave_requests', 'decision_note')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->string('decision_note')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_requests', 'decision_note')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropColumn('decision_note');
            });
        }

        $names = collect(Schema::getIndexes('leave_types'))->pluck('name');
        if ($names->contains('leave_types_company_id_department_id_code_unique')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'department_id', 'code']);
            });
        }
        if (! $names->contains('leave_types_company_id_code_unique')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->unique(['company_id', 'code']);
            });
        }
        if (Schema::hasColumn('leave_types', 'department_id')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }
    }
};
