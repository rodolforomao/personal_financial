<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('financial:daily')->dailyAt('06:00')->name('financial-daily-intelligence');
