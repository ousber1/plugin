<?php

namespace App\Services;

use App\Models\ArtworkFile;
use App\Models\CartItem;
use App\Models\OrderItem;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Private artwork storage + best-effort automated checks. Anything the
 * application cannot verify is flagged for manual prepress review, never
 * reported as "print-ready".
 */
class ArtworkService
{
    public function __construct(protected Settings $settings) {}

    public function rules(): array
    {
        $ext = implode(',', (array) $this->settings->get('uploads.allowed', ['pdf', 'png', 'jpg', 'jpeg']));
        $kb = (int) $this->settings->get('uploads.max_mb', 100) * 1024;

        return ['required', 'file', 'mimes:'.$ext, 'max:'.$kb];
    }

    public function store(UploadedFile $file, OrderItem|CartItem $owner, string $side = 'front'): ArtworkFile
    {
        $fk = $owner instanceof OrderItem ? 'order_item_id' : 'cart_item_id';
        $version = (int) ArtworkFile::where($fk, $owner->id)->where('side', $side)->max('version') + 1;
        $dir = $owner instanceof OrderItem ? 'artwork/order-'.$owner->order_id.'/item-'.$owner->id : 'artwork/cart-'.$owner->cart_id.'/item-'.$owner->id;
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs($dir, $side.'-v'.$version.'-'.Str::random(12).'.'.$ext, 'private');

        $w = $owner->width_mm ?? ($owner instanceof CartItem ? ($owner->selection['width'] ?? null) : null);
        $h = $owner->height_mm ?? ($owner instanceof CartItem ? ($owner->selection['height'] ?? null) : null);
        $checks = $this->inspect(Storage::disk('private')->path($path), $file->getMimeType(), $w ? (float) $w : null, $h ? (float) $h : null);

        $art = ArtworkFile::create([
            $fk => $owner->id, 'side' => $side, 'version' => $version, 'disk' => 'private', 'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 250), 'mime' => $file->getMimeType(),
            'size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()),
            'checks' => $checks, 'uploaded_by' => auth()->id(),
        ]);
        if ($owner instanceof OrderItem) {
            $owner->update(['artwork_status' => 'received']);
            $job = $owner->job;
            if ($job && $job->stage === 'artwork_pending') {
                app(ProductionService::class)->transition($job, 'prepress_review', 'Fichier client reçu (v'.$version.')');
            }
            AuditLogger::log('artwork.uploaded', $owner, null, ['file' => $art->id, 'side' => $side, 'version' => $version]);
        }

        return $art;
    }

    /**
     * @return array{items: array<int, array{key:string,status:string,message:string}>}
     *               status: ok | warning | manual
     */
    public function inspect(string $abs, ?string $mime, ?float $trimW, ?float $trimH): array
    {
        $items = [];
        $bleed = 3.0;
        $minDpi = (int) $this->settings->get('uploads.min_dpi', 250);
        $isImage = in_array($mime, ['image/png', 'image/jpeg']);
        if ($isImage) {
            $info = @getimagesize($abs);
            if (! $info) {
                $items[] = ['key' => 'readable', 'status' => 'warning', 'message' => 'Image illisible ou corrompue.'];
            } else {
                [$px, $py] = $info;
                $items[] = ['key' => 'pixels', 'status' => 'ok', 'message' => "Image {$px} × {$py} px."];
                if (($info['channels'] ?? 3) === 4 && $mime === 'image/jpeg') {
                    $items[] = ['key' => 'color', 'status' => 'ok', 'message' => 'JPEG en CMJN (4 canaux) détecté.'];
                } else {
                    $items[] = ['key' => 'color', 'status' => 'manual', 'message' => 'Image RVB : la conversion CMJN sera faite au prépresse, les couleurs peuvent varier.'];
                }
                if ($trimW && $trimH) {
                    $fullW = $trimW + 2 * $bleed;
                    $fullH = $trimH + 2 * $bleed;
                    // orientation-agnostic comparison
                    if (($px > $py) !== ($fullW > $fullH) && abs($px - $py) > 2) {
                        [$fullW, $fullH] = [$fullH, $fullW];
                    }
                    $dpiX = $px / ($fullW / 25.4);
                    $dpiY = $py / ($fullH / 25.4);
                    $dpi = (int) floor(min($dpiX, $dpiY));
                    $ratioFile = $px / max(1, $py);
                    $ratioTrim = $trimW / $trimH;
                    $ratioBleed = $fullW / $fullH;
                    $items[] = $dpi >= $minDpi
                        ? ['key' => 'resolution', 'status' => 'ok', 'message' => "Résolution effective ≈ {$dpi} dpi au format final (minimum {$minDpi})."]
                        : ['key' => 'resolution', 'status' => 'warning', 'message' => "Résolution effective ≈ {$dpi} dpi : inférieure au minimum recommandé ({$minDpi} dpi). Risque de flou."];
                    $close = fn ($a, $b) => abs($a - $b) / $b < 0.02;
                    if ($close($ratioFile, $ratioBleed) || $close($ratioFile, 1 / $ratioBleed)) {
                        $items[] = ['key' => 'proportions', 'status' => 'ok', 'message' => 'Proportions compatibles avec le format + fond perdu de 3 mm.'];
                    } elseif ($close($ratioFile, $ratioTrim) || $close($ratioFile, 1 / $ratioTrim)) {
                        $items[] = ['key' => 'proportions', 'status' => 'manual', 'message' => 'Proportions du format fini, sans fond perdu apparent : vérification prépresse nécessaire.'];
                    } else {
                        $items[] = ['key' => 'proportions', 'status' => 'warning', 'message' => 'Les proportions ne correspondent pas au format commandé.'];
                    }
                }
            }
        } elseif ($mime === 'application/pdf') {
            $head = (string) @file_get_contents($abs, false, null, 0, 2 * 1024 * 1024);
            if (! str_starts_with($head, '%PDF')) {
                $items[] = ['key' => 'readable', 'status' => 'warning', 'message' => 'Le fichier ne semble pas être un PDF valide.'];
            } else {
                $pages = preg_match_all('/\/Type\s*\/Page[^s]/', $head);
                $items[] = ['key' => 'pages', 'status' => $pages ? 'ok' : 'manual', 'message' => $pages ? "{$pages} page(s) détectée(s)." : 'Nombre de pages non déterminé (PDF compressé).'];
                if (preg_match('/\/MediaBox\s*\[\s*([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s*\]/', $head, $m) && $trimW && $trimH) {
                    $wmm = round(((float) $m[3] - (float) $m[1]) * 25.4 / 72, 1);
                    $hmm = round(((float) $m[4] - (float) $m[2]) * 25.4 / 72, 1);
                    $fits = fn ($a, $b) => abs($a - $b) <= 1.0;
                    $ok = ($fits($wmm, $trimW + 6) && $fits($hmm, $trimH + 6)) || ($fits($hmm, $trimW + 6) && $fits($wmm, $trimH + 6));
                    $trimOnly = ($fits($wmm, $trimW) && $fits($hmm, $trimH)) || ($fits($hmm, $trimW) && $fits($wmm, $trimH));
                    $items[] = ['key' => 'dimensions', 'status' => $ok ? 'ok' : ($trimOnly ? 'manual' : 'warning'),
                        'message' => "Page 1 : {$wmm} × {$hmm} mm".($ok ? ' (format + fond perdu 3 mm).' : ($trimOnly ? ' (format fini, fond perdu à vérifier).' : ' – ne correspond pas au format commandé.'))];
                } else {
                    $items[] = ['key' => 'dimensions', 'status' => 'manual', 'message' => 'Dimensions de page non lisibles automatiquement.'];
                }
                $items[] = ['key' => 'pdfx', 'status' => 'manual', 'message' => str_contains($head, 'GTS_PDFX') ? 'Marqueur PDF/X présent (conformité non vérifiée).' : 'Conformité PDF/X non vérifiée.'];
            }
            $items[] = ['key' => 'fonts', 'status' => 'manual', 'message' => 'Incorporation des polices et profil CMJN : contrôle manuel au prépresse.'];
        }
        $items[] = ['key' => 'disclaimer', 'status' => 'manual', 'message' => 'Contrôle automatique indicatif. Le fichier sera vérifié par notre équipe prépresse avant impression.'];

        return ['items' => $items, 'trim_mm' => [$trimW, $trimH], 'checked_at' => now()->toIso8601String()];
    }
}
