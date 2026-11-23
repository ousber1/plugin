<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Notifications\SimpleNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class QuoteRequestController extends Controller
{
    public function create(Request $request)
    {
        $product = $request->query('produit') ? Product::visible()->where('slug', $request->query('produit'))->first() : null;
        $products = Product::visible()->orderBy('slug')->get();

        return view('store.quote-request', compact('product', 'products'));
    }

    public function store(Request $request)
    {
        $max = (int) setting('uploads.max_mb', 100) * 1024;
        $data = $request->validate([
            'name' => 'required|string|max:120', 'company' => 'nullable|string|max:190', 'email' => 'required|email|max:190',
            'phone' => 'required|string|max:40', 'product_id' => 'nullable|exists:products,id', 'product_type' => 'required|string|max:190',
            'width' => 'nullable|numeric|min:0|max:100000', 'height' => 'nullable|numeric|min:0|max:100000', 'unit' => 'required|in:mm,cm',
            'quantity' => 'required|integer|min:1|max:10000000', 'material' => 'nullable|string|max:190', 'finishes' => 'nullable|string|max:190',
            'deadline' => 'nullable|date|after_or_equal:today', 'city' => 'nullable|string|max:80', 'notes' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array|max:5', 'attachments.*' => 'file|mimes:pdf,png,jpg,jpeg,ai,eps,zip|max:'.$max,
            'website' => 'nullable|max:0', // honeypot
        ]);
        unset($data['attachments'], $data['website']);
        $req = QuoteRequest::create($data + ['user_id' => auth()->id(), 'customer_id' => auth()->user()?->customer_id]);
        foreach ((array) $request->file('attachments', []) as $file) {
            $path = $file->store('quote-requests/'.$req->id, 'private');
            $req->attachments()->create(['disk' => 'private', 'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 250), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => auth()->id()]);
        }
        try {
            Notification::route('mail', $req->email)->notify(new SimpleNotification('quote_request', ['name' => $req->name], app()->getLocale()));
            if ($to = setting('contact.email')) {
                Notification::route('mail', $to)->notify(new SimpleNotification('quote_request', ['name' => 'équipe ('.$req->name.' – '.$req->product_type.')'], 'fr'));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('quote.request')->with('ok', __('Merci ! Votre demande de devis a bien été envoyée. Nous vous répondons rapidement.'));
    }
}
