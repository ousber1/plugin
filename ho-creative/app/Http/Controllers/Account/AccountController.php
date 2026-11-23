<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\Document;
use App\Models\Order;
use App\Models\Quote;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    protected function orders(Request $r)
    {
        $u = $r->user();

        return Order::query()->where(fn ($q) => $q->where('user_id', $u->id)->when($u->customer_id, fn ($w) => $w->orWhere('customer_id', $u->customer_id)));
    }

    public function dashboard(Request $request)
    {
        $orders = $this->orders($request)->with('items')->latest()->limit(5)->get();
        $pendingProofs = $this->orders($request)->whereHas('items', fn ($q) => $q->where('proof_status', 'pending'))->count();
        $awaitingArtwork = $this->orders($request)->where('status', '!=', 'cancelled')->whereHas('items', fn ($q) => $q->where('artwork_status', 'pending')->where('artwork_mode', 'later'))->count();
        $quotes = $request->user()->customer_id ? Quote::where('customer_id', $request->user()->customer_id)->where('status', 'sent')->count() : 0;

        return view('account.dashboard', compact('orders', 'pendingProofs', 'awaitingArtwork', 'quotes'));
    }

    public function profile(Request $request)
    {
        return view('account.profile', ['user' => $request->user(), 'customer' => $request->user()->customer]);
    }

    public function updateProfile(Request $request)
    {
        $u = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:120', 'phone' => 'nullable|string|max:40', 'locale' => 'required|in:fr,ar',
            'company_name' => 'nullable|string|max:190', 'ice' => 'nullable|string|max:30',
            'current_password' => 'nullable|required_with:password|current_password', 'password' => 'nullable|confirmed|min:8',
        ]);
        $u->fill(['name' => $data['name'], 'phone' => $data['phone'], 'locale' => $data['locale']]);
        if (! empty($data['password'])) {
            $u->password = $data['password'];
        }
        $u->save();
        $u->customer?->update(['name' => $data['name'], 'phone' => $data['phone'], 'company_name' => $data['company_name'], 'ice' => $data['ice'], 'type' => $data['company_name'] ? 'company' : 'individual']);
        $request->session()->put('locale', $data['locale']);

        return back()->with('ok', __('Profil mis à jour.'));
    }

    public function quotes(Request $request)
    {
        $quotes = $request->user()->customer_id
            ? Quote::where('customer_id', $request->user()->customer_id)->whereIn('status', ['sent', 'accepted', 'rejected', 'expired', 'converted'])->with('currentRevision')->latest()->paginate(20)
            : collect();

        return view('account.quotes', compact('quotes'));
    }

    public function invoices(Request $request)
    {
        $docs = Document::whereIn('type', ['invoice', 'credit_note', 'receipt', 'proforma'])
            ->whereIn('order_id', $this->orders($request)->pluck('id'))->latest('issued_at')->paginate(30);

        return view('account.invoices', compact('docs'));
    }

    public function designs(Request $request)
    {
        $designs = Design::where('user_id', $request->user()->id)->with('product')->latest()->paginate(20);

        return view('account.designs', compact('designs'));
    }
}
