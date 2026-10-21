<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Devices\DeviceSync;
use Illuminate\Console\Command;
use Throwable;

class SyncDevices extends Command
{
    protected $signature = 'devices:sync';

    protected $description = 'Pull terminal people, credentials, and punches into HRMS';

    public function handle(DeviceSync $sync): int
    {
        foreach (Device::query()->orderBy('id')->get() as $device) {
            try {
                $this->line($sync->run($device));
            } catch (Throwable $exception) {
                $this->error($device->name.': '.$exception->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
