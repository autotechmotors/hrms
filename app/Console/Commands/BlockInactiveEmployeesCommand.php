<?php

namespace App\Console\Commands;

use App\Services\Employees\InactivePunchBlocker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BlockInactiveEmployeesCommand extends Command
{
    protected $signature = 'employees:block-inactive';

    protected $description = 'Block employees who have not punched in or out within the company setting days';

    public function handle(InactivePunchBlocker $blocker): int
    {
        $key = 'hrms.block-inactive.'.now()->toDateString();
        if (Cache::has($key)) {
            $this->comment('Already checked today.');

            return self::SUCCESS;
        }

        $count = $blocker->apply();
        Cache::put($key, $count, now()->endOfDay());
        $this->info("Blocked {$count} inactive employee(s).");

        return self::SUCCESS;
    }
}
