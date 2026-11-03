<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('devices:sync')->everyMinute()->withoutOverlapping(5);

Schedule::command('dahua:ensure-listen')
    ->everyMinute()
    ->when(fn () => (bool) config('dahua.auto_listen'))
    ->withoutOverlapping();

Schedule::command('hikvision:pull')
    ->everyMinute()
    ->when(function () {
        if (trim((string) config('hikvision.password')) === '') {
            return false;
        }
        $port = config('hikvision.port');

        return (bool) config('hikvision.pull') || (is_numeric($port) && (int) $port > 0);
    })
    ->withoutOverlapping(5);
