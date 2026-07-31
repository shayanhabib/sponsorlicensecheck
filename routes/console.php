<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sponsors:update')->dailyAt('02:00');
