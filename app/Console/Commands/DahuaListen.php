<?php

namespace App\Console\Commands;

use App\Services\Devices\DahuaLog;
use App\Services\Devices\EzapiListener;
use Illuminate\Console\Command;
use Throwable;

class DahuaListen extends Command
{
    protected $signature = 'dahua:listen {--port= : HTTPS port (default DAHUA_PUSH_PORT)}';

    protected $description = 'Run the Dahua EZAPI HTTPS AutoRegister/Push listener (replaces AutoRegisterAndPush_Person.exe)';

    public function handle(EzapiListener $listener): int
    {
        $port = (int) ($this->option('port') ?: config('dahua.port', 60002));
        if ($port < 1 || $port > 65535) {
            $this->error('Invalid port.');

            return self::FAILURE;
        }

        $this->info('Starting Dahua EZAPI listener…');
        $this->line('Register IDs come from Devices in HRMS — match the terminal AutoRegister ID.');
        $this->newLine();

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use ($listener): void {
                $listener->stop();
            });
            pcntl_signal(SIGTERM, function () use ($listener): void {
                $listener->stop();
            });
        }

        try {
            $listener->start($port, function (string $message): void {
                $this->line('['.now()->format('H:i:s').'] '.$message);
            });
        } catch (Throwable $e) {
            DahuaLog::debug('listener failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Listener stopped.');

        return self::SUCCESS;
    }
}
