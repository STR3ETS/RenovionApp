<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Gespreksverslagen: transcriptie ophalen, verslag laten maken, oude opnames opruimen.
Schedule::command('meetings:process')->everyMinute()->withoutOverlapping();

if (config('renovion.modules.automations')) {
    Schedule::command('automations:run')->everyFifteenMinutes();
}
