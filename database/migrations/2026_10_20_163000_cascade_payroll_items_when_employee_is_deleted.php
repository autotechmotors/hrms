<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        $this->replace('CASCADE');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        $this->replace('RESTRICT');
    }

    private function replace(string $rule): void
    {
        $name = 'payroll_items_employee_id_foreign';
        $exists = DB::select(
            'select CONSTRAINT_NAME from information_schema.TABLE_CONSTRAINTS where TABLE_SCHEMA = database() and TABLE_NAME = ? and CONSTRAINT_NAME = ?',
            ['payroll_items', $name],
        );
        if ($exists !== []) {
            DB::statement('alter table payroll_items drop foreign key '.$name);
        }
        DB::statement('alter table payroll_items add constraint '.$name.' foreign key (employee_id) references employees (id) on delete '.$rule);
    }
};
