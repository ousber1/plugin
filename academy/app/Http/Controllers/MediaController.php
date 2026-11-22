<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Services\AccessService;
use App\Services\MediaService;
use Illuminate\Http\Request;

/**
 * Protected media. Every request is authorized here; files are never
 * reachable from the public web root.
 */
class MediaController extends Controller
{
    public function __construct(private AccessService $access, private MediaService $media) {}

    public function video(Request $request, Lesson $lesson)
    {
        abort_unless($lesson->video_path && $this->access->canViewLesson($request->user(), $lesson), 403);

        return $this->media->respond($lesson->video_disk, $lesson->video_path, null, 'video/mp4');
    }

    public function attachment(Request $request, LessonAttachment $attachment)
    {
        abort_unless($this->access->canViewLesson($request->user(), $attachment->lesson), 403);

        return $this->media->respond($attachment->disk, $attachment->path, $attachment->original_name, $attachment->mime);
    }
}
