<?php

namespace App\Console\Commands;

use App\Services\Employees\InactivePunchBlocker;
use Illuminate\Console\Command;

class BlockInactiveEmployeesCommand extends Command
{
    protected $signature = 'employees:block-inactive';

    protected $description = 'Block employees who have not punched in or out within the company setting days';

    public function handle(InactivePunchBlocker $blocker): int
    {
        $count = $blocker->apply();
        $this->info("Blocked {$count} inactive employee(s).");

        return self::SUCCESS;
    }
}
