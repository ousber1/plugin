<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use App\Services\Payments\CmiGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SandboxGateway;
use App\Support\Access;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public static function gateway(?string $name = null): ?PaymentGateway
    {
        $name ??= (string) setting('payments.online_provider', 'cmi');

        return match ($name) {
            'cmi' => new CmiGateway,
            'sandbox' => app()->environment('production') ? null : new SandboxGateway,
            default => null,
        };
    }

    public function start(Request $request, Order $order)
    {
        abort_unless(Access::canViewOrder($request->user(), $order), 404);
        abort_unless(setting('payments.online') && in_array($order->payment_status, ['unpaid', 'partial']) && $order->status !== 'cancelled', 404);
        $gw = self::gateway();
        abort_unless($gw, 503, 'Paiement en ligne non configuré.');
        $form = $gw->redirectForm($order, $order->balance());

        return view('store.payment-redirect', compact('form', 'order'));
    }

    /** Server-to-server callback: the ONLY place an online payment is confirmed. */
    public function callback(Request $request, string $provider, PaymentService $payments)
    {
        $gw = self::gateway($provider);
        abort_unless($gw, 404);
        $event = $gw->verifyCallback($request);
        if (! $event) {
            return response('INVALID SIGNATURE', 400);
        }
        $order = Order::where('number', $event['order_number'])->first();
        if (! $order) {
            return response($gw->callbackResponse(false), 200);
        }
        $amount = Money::min($event['amount'], Money::max($order->balance(), 0));
        $result = $amount->isPositive() || ! $event['success']
            ? $payments->handleProviderEvent($gw->name(), $event['event_id'], $order, (string) $amount, $event['success'], $request->except(['HASH', 'signature']), $event['reference'])
            : 'duplicate';

        return response($gw->callbackResponse($event['success'] && $result !== 'ignored'), 200)->header('Content-Type', 'text/plain');
    }

    public function return(Request $request)
    {
        $order = Order::where('number', (string) $request->query('order'))->first();
        $ok = $request->query('status') === 'ok';
        // The browser return is informational only; status comes from the callback.
        if ($order && Access::canViewOrder($request->user(), $order)) {
            $route = $order->user_id ? route('account.orders.show', $order) : route('guest.order', $order->guest_token);

            return redirect($route)->with($ok ? 'ok' : 'err', $ok ? __('Paiement transmis. La confirmation apparaît dès réception de la notification bancaire.') : __('Le paiement n\'a pas abouti. Vous pouvez réessayer ou choisir un autre moyen de paiement.'));
        }

        return redirect()->route('home');
    }

    public function sandbox(Request $request, Order $order)
    {
        abort_if(app()->environment('production'), 404);
        abort_unless(Access::canViewOrder($request->user(), $order), 404);

        return view('store.payment-sandbox', compact('order'));
    }

    public function sandboxPay(Request $request, Order $order, PaymentService $payments)
    {
        abort_if(app()->environment('production'), 404);
        abort_unless(Access::canViewOrder($request->user(), $order), 404);
        $p = ['event_id' => 'SBX-'.Str::upper(Str::random(10)), 'oid' => $order->number, 'amount' => $order->balance(), 'status' => $request->input('status') === 'fail' ? 'failed' : 'paid'];
        $p['signature'] = SandboxGateway::sign($p);
        $sub = Request::create(route('payment.callback', 'sandbox'), 'POST', $p);
        $this->callback($sub, 'sandbox', $payments);

        return redirect()->route('payment.return', ['order' => $order->number, 'status' => $p['status'] === 'paid' ? 'ok' : 'fail']);
    }
}
