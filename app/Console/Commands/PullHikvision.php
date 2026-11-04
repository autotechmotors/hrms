<?php

namespace App\Console\Commands;

use App\Services\Devices\HikvisionAttendance;
use App\Services\Devices\HikvisionAuthException;
use Illuminate\Console\Command;
use Throwable;

class PullHikvision extends Command
{
    protected $signature = 'hikvision:pull';

    protected $description = 'Pull Hikvision face scans into attendance';

    public function handle(HikvisionAttendance $attendance): int
    {
        $configured = \App\Models\Device::query()->get()->contains(fn ($device) => $device->isHikvision() && $device->terminalPassword() !== '');
        if (! $configured) {
            $this->error('Set the face terminal password in Dahua Devices, then run hikvision:pull again.');

            return self::FAILURE;
        }

        try {
            $result = $attendance->pull();
        } catch (HikvisionAuthException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result['paused']) {
            $this->warn('Hikvision login is paused after a refused password. Update HIKVISION_PASSWORD and try again.');
        }
        $this->info('Attendance punches saved: '.$result['stored'].'. New employees added: '.$result['added'].'.');
        if ($result['held'] > 0) {
            $this->warn($result['held'].' scans are still waiting on the live site. That site does not have these employee codes yet.');
        }

        return self::SUCCESS;
    }
}
