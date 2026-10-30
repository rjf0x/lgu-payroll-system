<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('besu:anchor --limit=20')->everyMinute()->withoutOverlapping()
    ->when(fn () => config('besu.enabled') && config('besu.automatic_anchoring'));
