<?php

$names = [
    'ensure-xampp.bat',
    'install-xampp-autostart.bat',
    'hrms-local-service.bat',
    'hrms-watchdog.bat',
    'install-hrms-autostart.bat',
    'uninstall-hrms-autostart.bat',
    'start-local-hrms.bat',
];

foreach ($names as $name) {
    $path = __DIR__.DIRECTORY_SEPARATOR.$name;
    if (! is_file($path)) {
        echo "skip {$name}\n";
        continue;
    }
    $body = str_replace("\r\n", "\n", (string) file_get_contents($path));
    file_put_contents($path, str_replace("\n", "\r\n", $body));
    echo "CRLF {$name}\n";
}
