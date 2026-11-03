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
        if ((string) config('hikvision.password') === '') {
            $this->error('Set HIKVISION_PASSWORD to the face terminal login, then run hikvision:pull again.');

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
