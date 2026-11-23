<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Proof;
use App\Services\ArtworkService;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Pricing\PricingEngine;
use App\Services\ProofService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected function own(Request $r, Order $order): Order
    {
        $u = $r->user();
        abort_unless(! $u->is_staff || $order->user_id === $u->id, 404);
        abort_unless($order->user_id === $u->id || ($u->customer_id && $order->customer_id === $u->customer_id), 404);

        return $order;
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $orders = Order::where(fn ($q) => $q->where('user_id', $u->id)->when($u->customer_id, fn ($w) => $w->orWhere('customer_id', $u->customer_id)))
            ->with('items')->latest()->paginate(15);

        return view('account.orders', compact('orders'));
    }

    public function show(Request $request, Order $order)
    {
        $this->own($request, $order)->load('items.proofs', 'items.artworkFiles', 'items.product', 'items.design', 'shipments', 'documents', 'payments');

        return view('account.order-show', ['order' => $order, 'guest' => false]);
    }

    public function upload(Request $request, Order $order, int $item, ArtworkService $artwork)
    {
        $this->own($request, $order);
        abort_if(in_array($order->status, ['cancelled', 'completed']), 403);
        $item = $order->items()->findOrFail($item);
        $request->validate(['file' => $artwork->rules(), 'side' => 'required|in:front,back']);
        $artwork->store($request->file('file'), $item, $request->input('side'));

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', __('Fichier reçu. Merci !'));
    }

    public function proof(Request $request, Proof $proof, ProofService $proofs)
    {
        $this->own($request, $proof->orderItem->order);
        $data = $request->validate(['decision' => 'required|in:approved,changes_requested', 'comment' => 'nullable|string|max:2000', 'confirm' => 'required_if:decision,approved']);
        $proofs->respond($proof, $data['decision'], $request->user(), $data['comment'] ?? null);

        return back()->with('ok', $data['decision'] === 'approved' ? __('BAT approuvé. Votre commande part en production.') : __('Vos corrections ont été transmises à notre équipe.'));
    }

    /** Reorder = rebuild the cart with CURRENT prices and option availability. */
    public function reorder(Request $request, Order $order, OrderService $orders, PricingEngine $engine, CartService $cart)
    {
        $this->own($request, $order)->load('items.product');
        $res = $orders->reorderLines($order, $engine);
        foreach ($res['lines'] as $l) {
            $cart->add($l['product']->id, $l['selection'], 'later', ['notes' => __('Renouvellement de la commande :n', ['n' => $order->number])]);
        }
        $msg = count($res['lines']) ? __(':n article(s) ajouté(s) au panier aux tarifs actuels.', ['n' => count($res['lines'])]) : __('Aucun article n\'a pu être ajouté.');

        return redirect()->route('cart')->with(count($res['issues']) ? 'warn' : 'ok', trim($msg.' '.implode(' ', $res['issues'])));
    }
}
