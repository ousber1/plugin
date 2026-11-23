<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment;
use App\Services\DocumentService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function payments(Request $request)
    {
        $this->authorize('finance.view');
        $payments = Payment::with('order.customer', 'recorder')
            ->when($request->query('method'), fn ($q, $m) => $q->where('method', $m))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest('paid_at')->paginate(50)->withQueryString();

        return view('admin.finance.payments', compact('payments'));
    }

    public function documents(Request $request)
    {
        $this->authorize('finance.view');
        $docs = Document::with('customer', 'order')->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('q'), fn ($q, $s) => $q->where('number', 'like', "%$s%"))->latest('issued_at')->paginate(50)->withQueryString();

        return view('admin.finance.documents', compact('docs'));
    }

    public function creditNote(Request $request, Document $document, DocumentService $docs)
    {
        $this->authorize('payments.refund');
        $v = $request->validate(['amount' => 'required|numeric|min:0.01', 'reason' => 'required|string|max:500']);
        $cn = $docs->creditNote($document, (string) $v['amount'], $v['reason']);

        return back()->with('ok', 'Avoir '.$cn->number.' émis. Enregistrez le remboursement éventuel dans les paiements de la commande.');
    }
}
