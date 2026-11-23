# Lesson videos

## Recommended format

Upload **MP4** files using **H.264 video + AAC audio**. This combination plays in all modern browsers on phones and computers.

| Setting | Recommendation |
|---|---|
| Container | MP4 (`.mp4`) |
| Video codec | H.264 (High or Main profile), `yuv420p` |
| Resolution | 1280×720 (720p) is a good balance; 1920×1080 for screen recordings with small text |
| Frame rate | 25 or 30 fps |
| Bitrate | 1.5–3 Mbps for 720p, 3–5 Mbps for 1080p |
| Audio | AAC, 128 kbps, 44.1 or 48 kHz |
| Fast start | Enabled (`moov` atom at the beginning) so playback starts before the whole file loads |

Convert any video with [FFmpeg](https://ffmpeg.org):

```bash
ffmpeg -i input.mov -c:v libx264 -preset slow -crf 23 -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart lesson.mp4
```

HandBrake users: choose the *Fast 720p30* preset and tick *Web Optimized*.

## How uploads work

1. In **Admin › Courses › Content**, open a lesson and choose an MP4 (or drag it onto the upload box).
2. The browser sends the file in chunks (`VIDEO_CHUNK_MB`, default 5 MB) and shows progress.
3. If the connection drops, the uploader retries automatically. If you choose the same file again within 24 hours, the upload resumes from the last byte the server received.
4. When all chunks arrive, the server checks the real file type (`fileinfo`) and the MP4 structure, reads the duration, stores the video privately, and only then replaces the lesson's previous video.
5. You can preview the video in the dashboard before publishing the lesson.

Clear Arabic errors are shown for: wrong format, file too large (`VIDEO_MAX_MB`), interrupted uploads, invalid MP4 content and storage failures. When an upload fails, the lesson keeps its current video.

## Playback and protection

- Videos are stored in `storage/app/private` (or a private bucket) and are never placed in `public/`.
- Every request to `/media/lessons/{id}/video` is authorized. **Preview** lessons of published courses are public; other lessons need an enrollment. Staff who manage the course can always preview.
- Local playback supports **HTTP byte ranges** (`206 Partial Content`), so students can seek and resume. The player also remembers the position in the student's browser.
- With private object storage, the app redirects to a **short-lived signed URL** after authorization.
- No streaming system can stop a determined user from screen-recording. The goal here is to keep media out of public URLs and tie access to accounts.
