<?php

use Illuminate\Support\Facades\Schedule;

// Add "* * * * * php /path/to/artisan schedule:run" to cron (see docs/INSTALL.md).
Schedule::command('academy:prune-uploads')->daily();
