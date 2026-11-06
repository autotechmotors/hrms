<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gatepass:expire')->everyMinute()->withoutOverlapping();

Schedule::command('employees:block-inactive')->dailyAt('00:10')->withoutOverlapping();

Schedule::command('devices:sync')->everyMinute()->withoutOverlapping(5);

Schedule::command('hikvision:pull')
    ->everyMinute()
    ->when(function () {
        $ready = \App\Models\Device::query()->get()->contains(function ($device) {
            return $device->isHikvision()
                && $device->terminalPassword() !== ''
                && ((int) $device->port > 0 || (bool) config('hikvision.pull') || (is_numeric(config('hikvision.port')) && (int) config('hikvision.port') > 0));
        });

        return $ready || (trim((string) config('hikvision.password')) !== '' && ((bool) config('hikvision.pull') || (is_numeric(config('hikvision.port')) && (int) config('hikvision.port') > 0)));
    })
    ->withoutOverlapping(5);
