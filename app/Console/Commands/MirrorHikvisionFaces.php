<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Devices\HikvisionCredentials;
use Illuminate\Console\Command;
use Throwable;

class MirrorHikvisionFaces extends Command
{
    protected $signature = 'hikvision:mirror-faces';

    protected $description = 'Copy the working IN terminal face onto the OUT terminal';

    public function handle(HikvisionCredentials $credentials): int
    {
        $devices = Device::query()->get()->filter(fn (Device $device) => $device->isHikvision());
        $from = $devices->first(fn (Device $device) => $device->punch_role === Device::ROLE_IN);
        $to = $devices->first(fn (Device $device) => $device->punch_role === Device::ROLE_OUT);
        if (! $from || ! $to) {
            $this->error('Add both the IN and OUT Hikvision terminals first.');

            return self::FAILURE;
        }
        if ($from->terminalPassword() === '' || $to->terminalPassword() === '') {
            $this->error('Set the face terminal password in Devices, then run hikvision:mirror-faces again.');

            return self::FAILURE;
        }

        set_time_limit(0);
        $this->info('Copying faces from '.$from->name.' to '.$to->name.'.');

        try {
            $result = $credentials->mirrorFaces($from, $to);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Faces restored: '.$result['restored'].'. Missing: '.$result['missing'].'. Failed: '.$result['failed'].'. People: '.$result['total'].'.');

        return $result['failed'] > 0 && $result['restored'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
