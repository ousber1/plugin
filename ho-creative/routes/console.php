<?php

use Illuminate\Support\Facades\Schedule;

/*
 * cPanel cron (every minute):
 *   * * * * * /usr/local/bin/php /home/USER/hocreative/artisan schedule:run >> /dev/null 2>&1
 * No long-running worker is needed: queued jobs (if QUEUE_CONNECTION=database)
 * are drained in bounded runs below.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping()->when(fn () => config('queue.default') !== 'sync');
Schedule::command('hocreative:daily')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('hocreative:backup')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('auth:clear-resets')->daily();
