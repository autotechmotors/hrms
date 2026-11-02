<?php

/**
 * One-time install:
 * 1) Windows Startup → hidden watchdog (login = HRMS auto ON)
 * 2) Task Scheduler every 5 min → ensure services (even if watchdog was killed)
 */

$scripts = str_replace('/', '\\', __DIR__);
$startup = getenv('APPDATA').'\\Microsoft\\Windows\\Start Menu\\Programs\\Startup';
$link = $startup.'\\HRMS Local Server.lnk';
$target = $scripts.'\\hrms-watchdog-hidden.vbs';
$service = $scripts.'\\hrms-local-service.bat';

if (! is_dir($startup)) {
    fwrite(STDERR, "Startup folder missing: {$startup}\n");
    exit(1);
}

$ps = <<<PS
\$s = (New-Object -ComObject WScript.Shell).CreateShortcut('{$link}');
\$s.TargetPath = '{$target}';
\$s.WorkingDirectory = '{$scripts}';
\$s.WindowStyle = 7;
\$s.Save();
Write-Output \$s.FullName
PS;

$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hrms-startup.ps1';
file_put_contents($tmp, $ps);
passthru('powershell -NoProfile -ExecutionPolicy Bypass -File '.escapeshellarg($tmp), $code);
@unlink($tmp);

if ($code !== 0 || ! is_file($link)) {
    fwrite(STDERR, "Failed to create Startup shortcut\n");
    exit(1);
}

echo "Startup OK: {$link}\n";

// Backup: every 5 minutes ensure ports (no window). Ignore failure if access denied.
$task = 'HRMS Keep Alive';
$tr = 'cmd /c "'.$service.'"';
$create = 'schtasks /Create /TN '.escapeshellarg($task)
    .' /TR '.escapeshellarg($tr)
    .' /SC MINUTE /MO 5 /F 2>&1';
exec($create, $out, $taskCode);
echo implode("\n", $out)."\n";
if ($taskCode === 0) {
    echo "Task Scheduler OK: {$task} (every 5 min)\n";
} else {
    echo "Task Scheduler skipped (optional). Startup watchdog is enough.\n";
}

// Start now (hidden)
pclose(popen('wscript.exe '.escapeshellarg($target), 'r'));
usleep(2500000);
echo "HRMS starting. Open http://192.168.1.68:8000\n";
echo "Close browser OK. If you stop PHP windows, watchdog restarts them.\n";
