<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Expose local HRMS over public HTTPS via Cloudflare quick tunnel (cloudflared).
 * Free, no account for *.trycloudflare.com. Data still stays on this PC.
 */
class HrmsShare extends Command
{
    protected $signature = 'hrms:share
        {--port=8000 : Local HRMS web port}
        {--host=127.0.0.1 : Local bind host to forward}';

    protected $description = 'Public HTTPS URL for this PC HRMS (cloudflared quick tunnel) — use from anywhere';

    public function handle(): int
    {
        $port = (int) $this->option('port');
        $host = (string) $this->option('host');
        $bin = $this->binaryPath();

        if (! $this->ensureBinary($bin)) {
            return self::FAILURE;
        }

        $target = sprintf('http://%s:%d', $host, $port);
        $this->info('Local HRMS stays on this PC. Tunnel only publishes HTTPS access.');
        $this->line('Forwarding: '.$target);
        $this->line('Device EZAPI stays on LAN '.$this->dahuaHint().' (not through this web tunnel).');
        $this->newLine();
        $this->comment('Wait for a line like: https://….trycloudflare.com');
        $this->comment('Ctrl+C to stop sharing.');
        $this->newLine();

        $process = new Process([$bin, 'tunnel', '--url', $target], base_path(), null, null, null);
        $process->start();

        while ($process->isRunning()) {
            $out = $process->getIncrementalOutput().$process->getIncrementalErrorOutput();
            if ($out !== '') {
                $this->output->write($out);
                if (preg_match('#https://[a-z0-9\-]+\.trycloudflare\.com#i', $out, $m)) {
                    $this->newLine();
                    $this->info('Open from anywhere: '.$m[0]);
                    $this->newLine();
                }
            }
            usleep(200000);
        }

        return $process->getExitCode() === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function dahuaHint(): string
    {
        return (string) config('dahua.host', '192.168.1.68').':'.(int) config('dahua.port', 60002);
    }

    private function binaryPath(): string
    {
        $dir = storage_path('bin');
        File::ensureDirectoryExists($dir);

        return $dir.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'cloudflared.exe' : 'cloudflared');
    }

    private function ensureBinary(string $bin): bool
    {
        if (is_file($bin) && filesize($bin) > 1000000) {
            return true;
        }

        $url = PHP_OS_FAMILY === 'Windows'
            ? 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe'
            : 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64';

        $this->warn('Downloading cloudflared (one time)…');
        $this->line($url);

        try {
            $ctx = stream_context_create([
                'http' => ['timeout' => 120, 'follow_location' => 1, 'user_agent' => 'hrms-share'],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $data = file_get_contents($url, false, $ctx);
        } catch (\Throwable $e) {
            $this->error('Download failed: '.$e->getMessage());

            return false;
        }

        if ($data === false || strlen($data) < 1000000) {
            $this->error('Download failed or file too small.');

            return false;
        }

        File::put($bin, $data);
        if (PHP_OS_FAMILY !== 'Windows') {
            @chmod($bin, 0755);
        }

        $this->info('Saved: '.$bin);

        return true;
    }
}
