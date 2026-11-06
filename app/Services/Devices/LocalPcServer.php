<?php

namespace App\Services\Devices;

use Illuminate\Support\Facades\File;

/**
 * Windows office-PC controls: Startup watchdog + keep-alive task (same as scripts/*.bat).
 */
class LocalPcServer
{
    public function available(): bool
    {
        return PHP_OS_FAMILY === 'Windows' && function_exists('exec');
    }

    /**
     * @return array{
     *     available: bool,
     *     autostart: bool,
     *     keep_alive_task: bool,
     *     mysql: bool,
     *     web: bool,
     *     host: string,
     *     web_port: int
     * }
     */
    public function status(): array
    {
        return [
            'available' => $this->available(),
            'autostart' => $this->startupLinkExists(),
            'keep_alive_task' => $this->taskExists(),
            'mysql' => $this->portListening(3306),
            'web' => $this->portListening((int) $this->webPort()),
            'host' => (string) parse_url((string) config('app.url', 'http://127.0.0.1:8000'), PHP_URL_HOST) ?: '127.0.0.1',
            'web_port' => $this->webPort(),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function enableAutostart(): array
    {
        if (! $this->available()) {
            return ['ok' => false, 'message' => 'Local PC auto-start only works on Windows.'];
        }

        $scripts = $this->scriptsDir();
        $target = $scripts.DIRECTORY_SEPARATOR.'hrms-watchdog-hidden.vbs';
        if (! is_file($target)) {
            return ['ok' => false, 'message' => 'Missing scripts/hrms-watchdog-hidden.vbs'];
        }

        $link = $this->startupLinkPath();
        File::ensureDirectoryExists(dirname($link));
        if (! $this->createShortcut($link, $target, $scripts)) {
            return ['ok' => false, 'message' => 'Could not create Windows Startup shortcut.'];
        }

        $this->createKeepAliveTask();
        $this->startNow();

        return ['ok' => true, 'message' => 'Auto-start ON. PC login will start MySQL check and HRMS in the background.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function disableAutostart(): array
    {
        if (! $this->available()) {
            return ['ok' => false, 'message' => 'Local PC auto-start only works on Windows.'];
        }

        $link = $this->startupLinkPath();
        if (is_file($link)) {
            @unlink($link);
        }
        $this->deleteKeepAliveTask();

        return ['ok' => true, 'message' => 'Auto-start OFF. Running services stay until you click Stop now or reboot.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function startNow(): array
    {
        if (! $this->available()) {
            return ['ok' => false, 'message' => 'Windows only.'];
        }

        $scripts = $this->scriptsDir();
        $service = $scripts.DIRECTORY_SEPARATOR.'hrms-local-service.bat';
        $watch = $scripts.DIRECTORY_SEPARATOR.'hrms-watchdog-hidden.vbs';

        if (! function_exists('popen')) {
            return ['ok' => false, 'message' => 'This server cannot start the office PC service.'];
        }
        if (is_file($watch)) {
            pclose(popen('wscript.exe //B '.escapeshellarg($watch), 'r'));
        } elseif (is_file($service)) {
            pclose(popen('cmd /c '.escapeshellarg($service), 'r'));
        } else {
            return ['ok' => false, 'message' => 'HRMS service scripts missing.'];
        }

        usleep(1200000);

        return ['ok' => true, 'message' => 'Start requested in background. Refresh in a few seconds.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function stopNow(): array
    {
        if (! $this->available()) {
            return ['ok' => false, 'message' => 'Windows only.'];
        }

        // Stop schedule workers; do not kill this web PHP blindly via taskkill /IM php.exe.
        $this->killByCommandLine('schedule:work');
        $this->killByCommandLine('hrms-watchdog.bat');

        usleep(400000);

        return ['ok' => true, 'message' => 'Scheduler stopped. Web UI may still run until the page finishes.'];
    }

    private function scriptsDir(): string
    {
        return str_replace('/', '\\', base_path('scripts'));
    }

    private function startupLinkPath(): string
    {
        $appData = (string) getenv('APPDATA');

        return $appData.'\\Microsoft\\Windows\\Start Menu\\Programs\\Startup\\HRMS Local Server.lnk';
    }

    private function startupLinkExists(): bool
    {
        return is_file($this->startupLinkPath());
    }

    private function webPort(): int
    {
        $url = (string) config('app.url', 'http://127.0.0.1:8000');
        $port = parse_url($url, PHP_URL_PORT);

        return is_int($port) ? $port : 8000;
    }

    private function portListening(int $port): bool
    {
        if (! $this->available()) {
            return false;
        }
        $lines = [];
        if (! $this->command('netstat -ano | findstr ":'.$port.' " | findstr LISTENING', $lines)) {
            return false;
        }

        return $lines !== [];
    }

    private function createShortcut(string $link, string $target, string $workDir): bool
    {
        $ps = <<<PS
\$s = (New-Object -ComObject WScript.Shell).CreateShortcut('{$link}');
\$s.TargetPath = '{$target}';
\$s.WorkingDirectory = '{$workDir}';
\$s.WindowStyle = 7;
\$s.Save();
PS;
        $tmp = storage_path('app/device-runtime/ui-startup.ps1');
        File::ensureDirectoryExists(dirname($tmp));
        File::put($tmp, $ps);
        if (! $this->command('powershell -NoProfile -ExecutionPolicy Bypass -File '.escapeshellarg($tmp).' 2>&1', $out, $code)) {
            @unlink($tmp);

            return false;
        }
        @unlink($tmp);

        return $code === 0 && is_file($link);
    }

    private function createKeepAliveTask(): void
    {
        $service = $this->scriptsDir().DIRECTORY_SEPARATOR.'hrms-local-service.bat';
        $tr = 'cmd /c "'.$service.'"';
        $this->command('schtasks /Create /TN "HRMS Keep Alive" /TR '.escapeshellarg($tr).' /SC MINUTE /MO 5 /F 2>&1');
    }

    private function deleteKeepAliveTask(): void
    {
        $this->command('schtasks /Delete /TN "HRMS Keep Alive" /F 2>&1');
    }

    private function taskExists(): bool
    {
        $out = [];
        $code = 1;

        return $this->command('schtasks /Query /TN "HRMS Keep Alive" 2>&1', $out, $code) && $code === 0;
    }

    private function killByCommandLine(string $needle): void
    {
        $needle = str_replace("'", "''", $needle);
        $ps = "Get-CimInstance Win32_Process | Where-Object { \$_.CommandLine -and \$_.CommandLine -like '*{$needle}*' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force -ErrorAction SilentlyContinue }";
        $tmp = storage_path('app/device-runtime/ui-stop.ps1');
        File::ensureDirectoryExists(dirname($tmp));
        File::put($tmp, $ps);
        $this->command('powershell -NoProfile -ExecutionPolicy Bypass -File '.escapeshellarg($tmp).' 2>&1');
        @unlink($tmp);
    }

    /**
     * @param  array<int, string>|null  $output
     */
    private function command(string $command, ?array &$output = null, ?int &$code = null): bool
    {
        if (! $this->available()) {
            return false;
        }

        $output ??= [];
        $code = 1;
        \exec($command, $output, $code);

        return true;
    }
}
