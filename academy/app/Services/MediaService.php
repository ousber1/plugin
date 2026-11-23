<?php

namespace App\Services;

use App\Models\Lesson;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Private media storage and authorized delivery.
 * Local disk: streamed with HTTP byte-range support (seeking/resume).
 * Object storage: redirect to a short-lived signed URL.
 */
class MediaService
{
    public function disk(): string
    {
        return config('academy.media_disk', 'local');
    }

    /**
     * Store an assembled video and attach it to the lesson. The previous file
     * is removed only after the new one is stored and the lesson is updated.
     */
    public function attachVideo(Lesson $lesson, string $tempPath, ?int $duration): void
    {
        $disk = $this->disk();
        $newPath = 'videos/'.$lesson->course_id.'/'.$lesson->id.'-'.Str::random(24).'.mp4';

        $stream = fopen($tempPath, 'rb');
        $stored = Storage::disk($disk)->writeStream($newPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (! $stored || ! Storage::disk($disk)->exists($newPath)) {
            throw new \RuntimeException('store_failed');
        }

        $old = $lesson->video_path ? [$lesson->video_disk, $lesson->video_path] : null;

        try {
            DB::transaction(function () use ($lesson, $disk, $newPath, $tempPath, $duration) {
                $lesson->forceFill([
                    'video_disk' => $disk,
                    'video_path' => $newPath,
                    'video_size' => filesize($tempPath),
                    'duration_seconds' => $duration ?: $lesson->duration_seconds,
                ])->save();
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($newPath);
            throw $e;
        }

        if ($old) {
            Storage::disk($old[0])->delete($old[1]);
        }
        Audit::log('lesson.video_replaced', $lesson, ['replaced' => (bool) $old]);
    }

    /** @return array{disk:string,path:string,original_name:string,mime:?string,size:int} */
    public function storeUpload(UploadedFile $file, string $folder): array
    {
        $disk = $this->disk();
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $path = $file->storeAs($folder, Str::random(32).'.'.$ext, ['disk' => $disk]);
        if (! $path) {
            throw new \RuntimeException('store_failed');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }

    public function delete(?string $disk, ?string $path): void
    {
        if ($disk && $path) {
            Storage::disk($disk)->delete($path);
        }
    }

    /** Authorized response for a private file. Caller must check permissions first. */
    public function respond(string $disk, string $path, ?string $downloadName = null, ?string $mime = null): Response
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            abort(404);
        }

        if (config("filesystems.disks.$disk.driver") !== 'local') {
            $options = $downloadName ? ['ResponseContentDisposition' => 'attachment; filename="'.addslashes($downloadName).'"'] : [];

            return redirect()->away($storage->temporaryUrl($path, now()->addMinutes(config('academy.signed_url_minutes')), $options))
                ->header('Cache-Control', 'no-store');
        }

        $response = new BinaryFileResponse($storage->path($path), 200, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => $mime ?: ($storage->mimeType($path) ?: 'application/octet-stream'),
        ], false, null, false, true);

        $name = str_replace(['/', '\\'], '-', $downloadName ?: basename($path));
        $fallback = trim(preg_replace('/[^A-Za-z0-9._-]+/', '_', Str::ascii($name)), '_') ?: 'file';
        $response->setContentDisposition(
            $downloadName ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $name,
            $fallback
        );

        return $response;
    }
}
