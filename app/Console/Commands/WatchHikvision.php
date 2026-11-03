<?php

namespace App\Console\Commands;

use App\Services\Devices\HikvisionAttendance;
use App\Services\Devices\HikvisionAuthException;
use Illuminate\Console\Command;
use Throwable;

class WatchHikvision extends Command
{
    protected $signature = 'hikvision:watch';

    protected $description = 'Keep pulling Hikvision face scans every few seconds';

    public function handle(HikvisionAttendance $attendance): int
    {
        if ((string) config('hikvision.password') === '') {
            $this->error('Set HIKVISION_PASSWORD to the face terminal login, then run hikvision:watch again.');

            return self::FAILURE;
        }

        $tick = 0;
        while (true) {
            try {
                $result = $attendance->pull($tick % 20 === 0);
                if ($result['stored'] > 0 || $result['added'] > 0) {
                    $this->info('Attendance punches saved: '.$result['stored'].'. New employees added: '.$result['added'].'.');
                }
            } catch (HikvisionAuthException $exception) {
                $this->error($exception->getMessage());
                sleep(30);
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());
            }

            $tick++;
            sleep(2);
        }
    }
}
