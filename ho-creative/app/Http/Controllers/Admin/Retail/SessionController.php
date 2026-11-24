<?php

namespace App\Http\Controllers\Admin\Retail;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Services\RetailService;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index()
    {
        $this->authorize('retail.cash');

        return view('admin.retail.sessions', ['sessions' => CashSession::with('opener', 'closer')->withCount('sales')->latest('opened_at')->paginate(30)]);
    }

    public function show(CashSession $session, RetailService $retail)
    {
        $this->authorize('retail.cash');
        $session->load('movements.user', 'opener', 'closer');

        return view('admin.retail.session', ['session' => $session, 'summary' => $retail->sessionSummary($session), 'sales' => $session->sales()->with('cashier')->latest()->get()]);
    }

    public function movement(Request $request, CashSession $session, RetailService $retail)
    {
        $this->authorize('retail.cash');
        $v = $request->validate(['type' => 'required|in:in,out', 'amount' => 'required|numeric|min:0.01', 'reason' => 'required|string|max:190', 'key' => 'required|uuid']);
        $retail->cashMovement($session, $v['type'], (string) $v['amount'], $v['reason'], 'cash:'.$v['key']);

        return back()->with('ok', $v['type'] === 'in' ? 'Entrée de caisse enregistrée.' : 'Sortie de caisse enregistrée.');
    }

    public function close(Request $request, CashSession $session, RetailService $retail)
    {
        $this->authorize('retail.cash');
        $v = $request->validate(['counted_cash' => 'required|numeric|min:0', 'notes' => 'nullable|string|max:1000']);
        $s = $retail->closeSession($session, (string) $v['counted_cash'], $v['notes'] ?? null, $request->user());

        return redirect()->route('admin.retail.sessions.show', $s)->with((float) $s->difference == 0.0 ? 'ok' : 'warn', 'Caisse clôturée. Écart : '.money($s->difference));
    }
}
