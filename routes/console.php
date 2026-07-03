<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Compatible con hosting compartido: Hostinger sólo necesita invocar
// `php artisan schedule:run` cada minuto desde su panel de cron jobs.
Schedule::command('queue:work database --queue=ai-text,ai-image --stop-when-empty --tries=3 --timeout=300 --max-time=240')
    ->everyMinute()
    ->withoutOverlapping(10);
