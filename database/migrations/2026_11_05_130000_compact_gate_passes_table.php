<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gate_passes') || Schema::hasColumn('gate_passes', 'number')) {
            return;
        }

        Schema::table('gate_passes', function (Blueprint $table) {
            $table->string('number', 24)->nullable()->after('company_id');
            $table->foreignId('decided_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by');
            $table->string('decision_note', 500)->nullable()->after('decided_at');
        });

        DB::table('gate_passes')->update([
            'number' => DB::raw('gate_pass_number'),
            'decided_by' => DB::raw('COALESCE(approved_by, rejected_by)'),
            'decided_at' => DB::raw('COALESCE(approved_at, rejected_at)'),
            'decision_note' => DB::raw('rejection_reason'),
        ]);

        Schema::table('gate_passes', function (Blueprint $table) {
            $table->unique('number');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'gate_pass_number',
                'requested_at',
                'approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }

    public function down(): void
    {
        //
    }
};
