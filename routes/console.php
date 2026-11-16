<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Settle mobile money prompts whose callback never arrived (run `php artisan schedule:run` every minute via cron).
Schedule::command('payments:sync')->everyMinute()->withoutOverlapping();
