<?php

namespace App\Console\Commands;

use App\Models\GatePass;
use Illuminate\Console\Command;

class ExpireGatePassesCommand extends Command
{
    protected $signature = 'gatepass:expire';

    protected $description = 'Expire approved Gate Passes, revoke OUT machine access, and notify still-out people';

    public function handle(): int
    {
        $count = GatePass::expireStale();
        $this->info("Expired {$count} gate pass(es).");

        return self::SUCCESS;
    }
}
