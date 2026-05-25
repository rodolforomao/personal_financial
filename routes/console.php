<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('financial:daily')->dailyAt('06:00')->name('financial-daily-intelligence');

if (config('financial.integrations.telegram.scheduled_poll', false)) {
    Schedule::command('telegram:poll --once')
        ->everyMinute()
        ->withoutOverlapping(2)
        ->name('telegram-poll-once');
}

if (config('financial.integrations.telegram.scheduled_queue', false)) {
    $queues = config('financial.integrations.telegram.queue_names', 'notifications,default');

    Schedule::command("queue:work --queue={$queues} --stop-when-empty --max-time=50 --tries=2")
        ->everyMinute()
        ->withoutOverlapping(2)
        ->name('telegram-queue-drain');
}

if (config('financial.integrations.gmail.scheduled_sync', false)) {
    $limit = (int) config('financial.integrations.gmail.sync_limit', 25);
    Schedule::command("gmail:sync --limit={$limit}")
        ->everyFifteenMinutes()
        ->withoutOverlapping(10)
        ->name('gmail-sync');
}
