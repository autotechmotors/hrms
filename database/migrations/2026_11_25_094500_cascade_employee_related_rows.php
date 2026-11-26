<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'employee_documents',
        'employment_events',
        'employee_exits',
        'employee_bank_accounts',
        'payment_batch_lines',
    ];

    public function up(): void
    {
        $this->replace('CASCADE');
    }

    public function down(): void
    {
        $this->replace('RESTRICT');
    }

    private function replace(string $rule): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->tables as $table) {
            $name = $table.'_employee_id_foreign';
            $exists = DB::select(
                'select CONSTRAINT_NAME from information_schema.TABLE_CONSTRAINTS where TABLE_SCHEMA = database() and TABLE_NAME = ? and CONSTRAINT_NAME = ?',
                [$table, $name],
            );
            if ($exists !== []) {
                DB::statement('alter table `'.$table.'` drop foreign key `'.$name.'`');
            }
            DB::statement(
                'alter table `'.$table.'` add constraint `'.$name.'` foreign key (`employee_id`) references `employees` (`id`) on delete '.$rule
            );
        }
    }
};
