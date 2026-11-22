<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lesson;
use App\Models\VideoUpload;
use App\Services\MediaService;
use App\Services\Mp4Inspector;
use Illuminate\Http\Request;

/**
 * Resumable chunked uploads so large MP4 files work on hosts with small
 * upload_max_filesize. Chunks are appended sequentially to a private temp
 * file; the client resumes from the server-reported byte offset.
 */
class VideoUploadController extends AdminController
{
    public function start(Request $request, Lesson $lesson)
    {
        $this->authorizeCourse($lesson->course);
        $maxBytes = config('academy.video.max_mb') * 1048576;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/\.mp4$/i'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
        ], [
            'name.regex' => 'صيغة غير مدعومة. ارفع ملف فيديو بصيغة MP4.',
            'size.max' => 'حجم الفيديو يتجاوز الحد المسموح ('.config('academy.video.max_mb').' م.ب).',
        ]);

        // Resume an unfinished upload of the same file by the same user.
        $upload = VideoUpload::where('lesson_id', $lesson->id)->where('user_id', $request->user()->id)
            ->where('status', 'uploading')->where('original_name', $data['name'])->where('total_size', $data['size'])
            ->where('updated_at', '>=', now()->subHours(config('academy.video.stale_upload_hours')))->latest()->first();

        if ($upload && (! is_file($upload->tempPath()) || filesize($upload->tempPath()) !== (int) $upload->received_bytes)) {
            $upload->received_bytes = is_file($upload->tempPath()) ? filesize($upload->tempPath()) : 0;
            $upload->save();
        }
        if (! $upload) {
            if (! is_dir(storage_path('app/chunks')) && ! @mkdir(storage_path('app/chunks'), 0775, true)) {
                return response()->json(['message' => 'مجلد التخزين المؤقت غير قابل للكتابة على الخادم.'], 500);
            }
            $upload = VideoUpload::create([
                'lesson_id' => $lesson->id, 'user_id' => $request->user()->id,
                'original_name' => $data['name'], 'total_size' => $data['size'], 'received_bytes' => 0,
            ]);
            touch($upload->tempPath());
        }

        return response()->json([
            'id' => $upload->id,
            'received' => (int) $upload->received_bytes,
            'chunk_url' => route('admin.uploads.chunk', $upload),
            'status_url' => route('admin.uploads.status', $upload),
            'complete_url' => route('admin.uploads.complete', $upload),
            'cancel_url' => route('admin.uploads.cancel', $upload),
        ]);
    }

    public function chunk(Request $request, VideoUpload $upload)
    {
        $this->authorizeUpload($request, $upload);
        if (! $request->hasFile('chunk') || ! $request->file('chunk')->isValid()) {
            return response()->json(['message' => 'لم يصل جزء الملف إلى الخادم. قد يكون حجم الجزء أكبر من حد PHP (upload_max_filesize).'], 422);
        }
        $offset = (int) $request->input('offset', -1);
        $current = filesize($upload->tempPath());
        if ($offset !== $current) {
            return response()->json(['message' => 'offset_mismatch', 'received' => $current], 409);
        }
        $chunk = $request->file('chunk');
        if ($chunk->getSize() > config('academy.video.chunk_mb') * 1048576 + 1024 || $current + $chunk->getSize() > $upload->total_size) {
            return response()->json(['message' => 'حجم الجزء غير صالح.'], 422);
        }
        if ($current === 0 && ! $this->looksLikeMp4($chunk->getRealPath())) {
            $upload->update(['status' => 'failed']);

            return response()->json(['message' => 'الملف ليس فيديو MP4 صالحًا.'], 422);
        }

        $in = fopen($chunk->getRealPath(), 'rb');
        $out = fopen($upload->tempPath(), 'ab');
        if (! $in || ! $out || ! flock($out, LOCK_EX)) {
            return response()->json(['message' => 'تعذّر الكتابة في ملف الرفع المؤقت.'], 500);
        }
        stream_copy_to_stream($in, $out);
        fflush($out);
        flock($out, LOCK_UN);
        fclose($in);
        fclose($out);
        clearstatcache(true, $upload->tempPath());

        $upload->received_bytes = filesize($upload->tempPath());
        $upload->save();

        return response()->json(['received' => (int) $upload->received_bytes]);
    }

    public function status(Request $request, VideoUpload $upload)
    {
        $this->authorizeUpload($request, $upload);
        clearstatcache(true, $upload->tempPath());

        return response()->json(['received' => is_file($upload->tempPath()) ? filesize($upload->tempPath()) : 0, 'status' => $upload->status]);
    }

    public function complete(Request $request, VideoUpload $upload, Mp4Inspector $inspector, MediaService $media)
    {
        $this->authorizeUpload($request, $upload);
        clearstatcache(true, $upload->tempPath());
        $path = $upload->tempPath();
        if (! is_file($path) || filesize($path) !== (int) $upload->total_size) {
            return response()->json(['message' => 'الرفع غير مكتمل. أعد اختيار الملف لاستئنافه.'], 422);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $info = $inspector->inspect($path);
        if (! in_array($mime, config('academy.video.mimes'), true) || ! $info['valid']) {
            @unlink($path);
            $upload->update(['status' => 'failed']);

            return response()->json(['message' => 'الملف المرفوع ليس فيديو MP4 صالحًا.'], 422);
        }

        try {
            $media->attachVideo($upload->lesson, $path, $info['duration']);
        } catch (\Throwable $e) {
            report($e);
            $upload->update(['status' => 'failed']);

            return response()->json(['message' => 'فشل حفظ الفيديو. لم يتغير الفيديو الحالي للدرس.'], 500);
        }
        @unlink($path);
        $upload->update(['status' => 'completed']);

        return response()->json([
            'ok' => true,
            'duration' => $info['duration'],
            'faststart' => $info['faststart'],
        ]);
    }

    public function cancel(Request $request, VideoUpload $upload)
    {
        $this->authorizeUpload($request, $upload);
        @unlink($upload->tempPath());
        $upload->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeUpload(Request $request, VideoUpload $upload): void
    {
        abort_unless($upload->user_id === $request->user()->id && $upload->status === 'uploading', 404);
        $this->authorizeCourse($upload->lesson->course);
    }

    private function looksLikeMp4(string $path): bool
    {
        $fh = fopen($path, 'rb');
        $head = fread($fh, 12);
        fclose($fh);

        return strlen($head) >= 8 && substr($head, 4, 4) === 'ftyp';
    }
}
