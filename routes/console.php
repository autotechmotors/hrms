<?php

use App\Models\Device;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gatepass:expire')->everyMinute()->withoutOverlapping();

// Once each day, the first time the scheduler is awake. Uses the days saved in Settings.
Schedule::command('employees:block-inactive')->everyFifteenMinutes()->withoutOverlapping();

Schedule::command('devices:sync')->everyMinute()->withoutOverlapping(5);

Schedule::command('hikvision:pull')
    ->everyMinute()
    ->when(function () {
        $ready = Device::query()->get()->contains(function ($device) {
            return $device->isHikvision()
                && $device->terminalPassword() !== ''
                && ((int) $device->port > 0 || (bool) config('hikvision.pull') || (is_numeric(config('hikvision.port')) && (int) config('hikvision.port') > 0));
        });

        return $ready || (trim((string) config('hikvision.password')) !== '' && ((bool) config('hikvision.pull') || (is_numeric(config('hikvision.port')) && (int) config('hikvision.port') > 0)));
    })
    ->withoutOverlapping(5);
