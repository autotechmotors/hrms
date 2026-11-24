<?php

namespace App\Console\Commands;

use App\Services\Devices\FaceAccess;
use Illuminate\Console\Command;

class SyncFaceAccessCommand extends Command
{
    protected $signature = 'faces:sync';

    protected $description = 'Set the out terminal to blocklist after in, and normal for a pass or the out window';

    public function handle(FaceAccess $faces): int
    {
        $count = $faces->syncOpen();
        $this->info("Checked {$count} open attendance day(s).");

        return self::SUCCESS;
    }
}
