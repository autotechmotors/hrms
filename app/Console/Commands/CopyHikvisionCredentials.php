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
        if (trim((string) config('hikvision.password')) === '') {
            $this->error('Set HIKVISION_PASSWORD to the face terminal login, then run hikvision:credentials again.');

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

        $this->info('Faces saved: '.$result['faces'].'. Fingers saved: '.$result['fingers'].'.');

        return self::SUCCESS;
    }
}
