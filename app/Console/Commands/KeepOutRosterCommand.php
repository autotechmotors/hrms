<?php

namespace App\Console\Commands;

use App\Services\Devices\KeepOutRoster;
use Illuminate\Console\Command;
use Throwable;

class KeepOutRosterCommand extends Command
{
    protected $signature = 'hikvision:keep-out {--apply : Delete everyone who is not on the OUT terminal}';

    protected $description = 'Keep only the OUT terminal people and remove everyone else';

    public function handle(KeepOutRoster $roster): int
    {
        set_time_limit(0);

        try {
            $result = $roster->run((bool) $this->option('apply'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('OUT people kept: '.$result['kept'].'.');
        $this->info('Faces still stored: '.$result['faces'].'. Fingers still stored: '.$result['fingers'].'.');
        $this->info(($this->option('apply') ? 'Employees removed: ' : 'Employees that would be removed: ').$result['removed'].'.');
        $this->info(($this->option('apply') ? 'Removed from the other terminals: ' : 'Other-terminal people that would be removed: ').$result['device_removed'].'.');
        if ($result['duplicates'] === []) {
            $this->info('Duplicate employee codes: none.');
        } else {
            $this->warn('Duplicate employee codes: '.implode(', ', $result['duplicates']));
        }
        if ($result['names'] !== []) {
            $this->warn('Same name on more than one OUT code: '.implode(' | ', $result['names']));
        }
        if (! $this->option('apply')) {
            $this->comment('Nothing was deleted. Run php artisan hikvision:keep-out --apply to remove the extra people.');
        }

        return self::SUCCESS;
    }
}
