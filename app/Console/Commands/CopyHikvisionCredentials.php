<?php

namespace App\Console\Commands;

use App\Services\Devices\HikvisionAuthException;
use App\Services\Devices\HikvisionCredentials;
use Illuminate\Console\Command;
use Throwable;

class CopyHikvisionCredentials extends Command
{
    protected $signature = 'hikvision:credentials';

    protected $description = 'Copy faces and fingers from the Hikvision terminal onto matching employees';

    public function handle(HikvisionCredentials $credentials): int
    {
        $configured = \App\Models\Device::query()->get()->contains(fn ($device) => $device->isHikvision() && $device->terminalPassword() !== '');
        if (! $configured) {
            $this->error('Set the face terminal password in Devices, then run hikvision:credentials again.');

            return self::FAILURE;
        }

        set_time_limit(0);

        try {
            $result = $credentials->copyMissing();
        } catch (HikvisionAuthException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Employees added: '.$result['added'].'. Faces saved: '.$result['faces'].'. Fingers saved: '.$result['fingers'].'.');

        return self::SUCCESS;
    }
}
