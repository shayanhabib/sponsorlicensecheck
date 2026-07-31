<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sponsors:update')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onFailure(fn (): bool => logger()->error('Scheduled sponsor import failed') === null);
