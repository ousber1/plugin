<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Proof;
use App\Services\ArtworkService;
use App\Services\ProofService;
use Illuminate\Http\Request;

/** Secret-link order tracking for guest checkouts. */
class GuestOrderController extends Controller
{
    protected function order(string $token): Order
    {
        abort_if(strlen($token) < 40, 404);
        $order = Order::where('guest_token', $token)->firstOrFail();
        $tokens = (array) session('guest_tokens', []);
        if (! in_array($token, $tokens, true)) {
            session()->put('guest_tokens', array_slice([...$tokens, $token], -20));
        }

        return $order;
    }

    public function show(string $token)
    {
        $order = $this->order($token)->load('items.proofs', 'items.artworkFiles', 'items.product', 'shipments', 'documents');

        return view('account.order-show', ['order' => $order, 'guest' => true]);
    }

    public function upload(Request $request, string $token, int $item, ArtworkService $artwork)
    {
        $order = $this->order($token);
        $item = $order->items()->findOrFail($item);
        abort_if(in_array($order->status, ['cancelled', 'completed']), 403);
        $request->validate(['file' => $artwork->rules(), 'side' => 'required|in:front,back']);
        $artwork->store($request->file('file'), $item, $request->input('side'));

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', __('Fichier reçu. Merci !'));
    }

    public function proof(Request $request, string $token, Proof $proof, ProofService $proofs)
    {
        $order = $this->order($token);
        abort_unless($proof->orderItem->order_id === $order->id, 404);
        $data = $request->validate(['decision' => 'required|in:approved,changes_requested', 'comment' => 'nullable|string|max:2000', 'confirm' => 'required_if:decision,approved']);
        $proofs->respond($proof, $data['decision'], null, $data['comment'] ?? null);

        return back()->with('ok', $data['decision'] === 'approved' ? __('BAT approuvé. Votre commande part en production.') : __('Vos corrections ont été transmises à notre équipe.'));
    }
}
