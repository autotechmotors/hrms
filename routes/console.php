<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('devices:sync')->everyMinute()->withoutOverlapping();

Schedule::command('dahua:ensure-listen')
    ->everyMinute()
    ->when(fn () => (bool) config('dahua.auto_listen'))
    ->withoutOverlapping();
