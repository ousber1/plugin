<?php

namespace App\Services;

/**
 * Minimal ISO-BMFF reader: validates that a file is an MP4 container and
 * extracts its duration from the moov/mvhd box. Pure PHP, no ffmpeg needed.
 */
class Mp4Inspector
{
    /** @return array{valid:bool, duration:?int, faststart:bool} */
    public function inspect(string $path): array
    {
        $result = ['valid' => false, 'duration' => null, 'faststart' => false];
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return $result;
        }
        $size = filesize($path);
        $offset = 0;
        $first = true;
        $seenMdat = false;

        while ($offset + 8 <= $size) {
            fseek($fh, $offset);
            $header = fread($fh, 8);
            if (strlen($header) < 8) {
                break;
            }
            $boxSize = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);
            $headerLen = 8;
            if ($boxSize === 1) {
                $big = fread($fh, 8);
                $boxSize = (int) (unpack('J', $big)[1]);
                $headerLen = 16;
            } elseif ($boxSize === 0) {
                $boxSize = $size - $offset;
            }
            if ($first) {
                if ($type !== 'ftyp') {
                    break;
                }
                $result['valid'] = true;
                $first = false;
            }
            if ($type === 'mdat') {
                $seenMdat = true;
            }
            if ($type === 'moov') {
                $result['faststart'] = ! $seenMdat;
                $result['duration'] = $this->readMvhd($fh, $offset + $headerLen, $boxSize - $headerLen);
            }
            if ($boxSize < 8) {
                break;
            }
            $offset += $boxSize;
        }
        fclose($fh);

        return $result;
    }

    private function readMvhd($fh, int $start, int $length): ?int
    {
        $offset = $start;
        $end = $start + $length;
        while ($offset + 8 <= $end) {
            fseek($fh, $offset);
            $header = fread($fh, 8);
            $boxSize = unpack('N', substr($header, 0, 4))[1];
            if (substr($header, 4, 4) === 'mvhd') {
                $version = ord(fread($fh, 1));
                fread($fh, 3);
                if ($version === 1) {
                    fread($fh, 16);
                    $scale = unpack('N', fread($fh, 4))[1];
                    $duration = unpack('J', fread($fh, 8))[1];
                } else {
                    fread($fh, 8);
                    $scale = unpack('N', fread($fh, 4))[1];
                    $duration = unpack('N', fread($fh, 4))[1];
                }

                return $scale > 0 ? (int) round($duration / $scale) : null;
            }
            if ($boxSize < 8) {
                return null;
            }
            $offset += $boxSize;
        }

        return null;
    }
}
