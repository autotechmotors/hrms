<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->foreignId('employee_type_id')->nullable()->constrained('employee_types')->restrictOnDelete();
        });

        $types = DB::table('employee_types')->orderBy('id')->get()->groupBy(fn ($row) => (int) $row->company_id);

        foreach (DB::table('designations')->whereNull('employee_type_id')->get() as $designation) {
            $group = $types[(int) $designation->company_id] ?? null;
            $typeId = $group[0]->id ?? null;
            if ($typeId) {
                DB::table('designations')->where('id', $designation->id)->update(['employee_type_id' => $typeId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_type_id');
        });
    }
};
