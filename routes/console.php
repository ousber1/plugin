<?php

use Illuminate\Support\Facades\Schedule;

// Analyze ads performance every 6 hours
Schedule::command('ads:analyze')->everySixHours();

// Check low stock daily at 8 AM
Schedule::command('stock:check')->dailyAt('08:00');

// Sync ads data from APIs every 4 hours
Schedule::command('ads:sync')->everyFourHours();
