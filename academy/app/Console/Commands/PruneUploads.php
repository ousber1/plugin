<?php

namespace App\Console\Commands;

use App\Models\VideoUpload;
use Illuminate\Console\Command;

class PruneUploads extends Command
{
    protected $signature = 'academy:prune-uploads';

    protected $description = 'Remove abandoned partial video uploads';

    public function handle(): int
    {
        $count = 0;
        VideoUpload::where('updated_at', '<', now()->subHours(config('academy.video.stale_upload_hours')))
            ->each(function (VideoUpload $upload) use (&$count) {
                @unlink($upload->tempPath());
                $upload->delete();
                $count++;
            });
        $this->info("Pruned {$count} stale uploads.");

        return self::SUCCESS;
    }
}
