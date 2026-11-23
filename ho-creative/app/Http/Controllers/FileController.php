<?php

namespace App\Http\Controllers;

use App\Models\ArtworkFile;
use App\Models\Attachment;
use App\Models\Design;
use App\Models\Document;
use App\Models\Proof;
use App\Services\CartService;
use App\Services\DocumentService;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Every private file is streamed through here after a record-level check.
 * Files live under storage/app/private (outside the web root).
 */
class FileController extends Controller
{
    protected function stream(string $disk, string $path, string $name, bool $inline = true)
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, $name, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }

    public function artwork(Request $request, ArtworkFile $file)
    {
        $u = $request->user();
        if ($file->order_item_id) {
            abort_unless(Access::canViewOrder($u, $file->orderItem->order), 404);
        } else {
            $cart = app(CartService::class)->current();
            abort_unless(($cart && $file->cartItem && $file->cartItem->cart_id === $cart->id) || Access::staffCan($u, ['orders.manage']), 404);
        }

        return $this->stream($file->disk, $file->path, $file->original_name, ! $request->boolean('download'));
    }

    public function proof(Request $request, Proof $proof)
    {
        abort_unless(Access::canViewOrder($request->user(), $proof->orderItem->order), 404);

        return $this->stream('private', $proof->path, 'BAT-v'.$proof->version.'-'.$proof->original_name);
    }

    public function document(Request $request, Document $document, DocumentService $docs)
    {
        $u = $request->user();
        $ok = match (true) {
            $document->order_id !== null => Access::canViewOrder($u, $document->order) && ($u?->is_staff ? Access::staffCan($u, ['finance.view', 'orders.manage', 'documents.issue', 'delivery.manage']) : true),
            $document->quote_id !== null => Access::canViewQuote($u, $document->quote),
            default => Access::staffCan($u, ['purchasing.manage', 'finance.view']),
        };
        // customers never see internal purchase orders
        abort_unless($ok && ($document->type !== 'purchase_order' || $u?->is_staff), 404);

        return response($docs->renderPdf($document), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$document->number.'.pdf"', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function design(Request $request, Design $design, string $side)
    {
        $u = $request->user();
        $owner = ($u && $design->user_id === $u->id) || ($design->session_key && $design->session_key === $request->session()->getId())
            || in_array($design->id, (array) session('designs', []), true)
            || Access::staffCan($u, ['orders.manage', 'artwork.manage', 'templates.manage'])
            || \App\Models\OrderItem::where('design_id', $design->id)->get()->contains(fn ($i) => Access::canViewOrder($u, $i->order));
        abort_unless($owner, 404);
        $path = $design->exports[$side] ?? null;
        abort_unless($path, 404);

        return $this->stream('private', $path, 'design-'.$side.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }

    public function attachment(Request $request, Attachment $attachment)
    {
        abort_unless(Access::staffCan($request->user(), ['quotes.manage', 'orders.manage']), 404);

        return $this->stream($attachment->disk, $attachment->path, $attachment->original_name, false);
    }
}
