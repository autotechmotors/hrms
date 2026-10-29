<?php

namespace App\Console\Commands;

use App\Services\Devices\DahuaLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class EnsureDahuaListen extends Command
{
    protected $signature = 'dahua:ensure-listen';

    protected $description = 'Start dahua:listen in the background if the push port is not already open (for schedule/cron)';

    public function handle(): int
    {
        if (! config('dahua.auto_listen')) {
            $this->line('DAHUA_AUTO_LISTEN is off — skip.');

            return self::SUCCESS;
        }

        $port = (int) config('dahua.port', 60002);

        // Shared hosting / live site already serves HTTPS on 80/443 — never bind those.
        if (in_array($port, [80, 443], true) || app()->environment('production')) {
            $this->warn('Auto-listen skipped on production / port '.$port.' (use website :443 EZAPI routes).');
            DahuaLog::debug('ensure-listen skipped', [
                'port' => $port,
                'env' => app()->environment(),
            ]);

            return self::SUCCESS;
        }

        if ($this->portOpen($port)) {
            $this->line("Listener already on port {$port}.");

            return self::SUCCESS;
        }

        $this->startDetached($port);
        usleep(800000);

        if ($this->portOpen($port)) {
            $this->info("Started dahua:listen on port {$port}.");
            DahuaLog::debug('ensure-listen started', ['port' => $port]);

            return self::SUCCESS;
        }

        $this->error("Could not start dahua:listen on port {$port}. Check storage/logs/dahua-listen.out");
        DahuaLog::debug('ensure-listen failed', ['port' => $port]);

        return self::FAILURE;
    }

    private function portOpen(int $port): bool
    {
        // Do not TCP-connect: that breaks the TLS listener and spams handshake errors.
        if (PHP_OS_FAMILY === 'Windows') {
            exec('netstat -ano | findstr ":'.$port.' " | findstr LISTENING', $lines);

            return $lines !== [];
        }

        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.4);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    private function startDetached(int $port): void
    {
        File::ensureDirectoryExists(storage_path('logs'));
        File::ensureDirectoryExists(storage_path('app/dahua/runtime'));

        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $log = storage_path('logs/dahua-listen.out');
        $pidFile = storage_path('app/dahua/runtime/listener.pid');
        $cwd = base_path();

        if (PHP_OS_FAMILY === 'Windows') {
            $ps = sprintf(
                'Start-Process -FilePath %s -ArgumentList %s -WorkingDirectory %s -WindowStyle Hidden -RedirectStandardOutput %s -RedirectStandardError %s',
                escapeshellarg($php),
                escapeshellarg('artisan dahua:listen --port='.$port),
                escapeshellarg($cwd),
                escapeshellarg($log),
                escapeshellarg($log)
            );
            pclose(popen('powershell -NoProfile -Command '.$ps, 'r'));
            File::put($pidFile, (string) time());

            return;
        }

        $cmd = escapeshellarg($php).' '.escapeshellarg($artisan).' dahua:listen --port='.$port
            .' >> '.escapeshellarg($log).' 2>&1 & echo $!';
        $pid = trim((string) shell_exec($cmd));
        if ($pid !== '') {
            File::put($pidFile, $pid);
        }
    }
}
