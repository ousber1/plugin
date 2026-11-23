<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Services\DocumentService;
use App\Services\QuoteService;
use Illuminate\Http\Request;

/** Secure customer link for viewing and accepting/rejecting a quote. */
class PublicQuoteController extends Controller
{
    protected function quote(string $token): Quote
    {
        abort_if(strlen($token) < 40, 404);
        $quote = Quote::where('token', $token)->whereIn('status', ['sent', 'accepted', 'rejected', 'expired', 'converted'])->firstOrFail();
        session()->put('quote_tokens', array_slice(array_unique([...(array) session('quote_tokens', []), $token]), -20));

        return $quote;
    }

    public function show(string $token)
    {
        $quote = $this->quote($token)->load('customer', 'revisions.items.product');
        $rev = $quote->revisionNo($quote->current_revision);

        return view('store.public-quote', compact('quote', 'rev'));
    }

    public function respond(Request $request, string $token, QuoteService $quotes)
    {
        $quote = $this->quote($token);
        $data = $request->validate(['decision' => 'required|in:accept,reject', 'name' => 'required|string|max:120', 'note' => 'nullable|string|max:1000', 'revision' => 'required|integer']);
        if ((int) $data['revision'] !== $quote->current_revision) {
            return back()->with('err', __('Ce devis a été mis à jour entre-temps. Merci de relire la dernière version.'));
        }
        $quotes->respond($quote, $data['decision'], $data['name'], $data['note'] ?? null);

        return back()->with('ok', $data['decision'] === 'accept' ? __('Merci ! Votre acceptation est enregistrée. Nous lançons votre commande.') : __('Votre réponse a été enregistrée.'));
    }

    public function pdf(string $token, DocumentService $docs)
    {
        $quote = $this->quote($token);
        $doc = $docs->quote($quote, $quote->revisionNo($quote->current_revision));

        return response($docs->renderPdf($doc), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$doc->number.'.pdf"']);
    }
}
