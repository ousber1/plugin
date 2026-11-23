<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Customer;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    protected function customer(Request $r): Customer
    {
        $u = $r->user();
        if (! $u->customer) {
            $c = Customer::create(['name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'source' => 'web']);
            $u->update(['customer_id' => $c->id]);
            $u->refresh();
        }

        return $u->customer;
    }

    protected function rules(): array
    {
        return ['label' => 'nullable|string|max:60', 'type' => 'required|in:shipping,billing', 'name' => 'required|string|max:120', 'company' => 'nullable|string|max:190',
            'line1' => 'required|string|max:190', 'line2' => 'nullable|string|max:190', 'city' => 'required|string|max:80', 'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:40', 'is_default' => 'boolean'];
    }

    protected function own(Request $r, Address $a): Address
    {
        abort_unless($a->customer_id === $this->customer($r)->id, 404);

        return $a;
    }

    public function index(Request $request)
    {
        return view('account.addresses', ['addresses' => $this->customer($request)->addresses()->get()]);
    }

    public function create()
    {
        return view('account.address-form', ['address' => new Address(['type' => 'shipping'])]);
    }

    public function store(Request $request)
    {
        $c = $this->customer($request);
        $a = $c->addresses()->create($request->validate($this->rules()));
        if ($a->is_default) {
            $c->addresses()->whereKeyNot($a->id)->where('type', $a->type)->update(['is_default' => false]);
        }

        return redirect()->route('account.addresses.index')->with('ok', __('Adresse enregistrée.'));
    }

    public function edit(Request $request, Address $address)
    {
        return view('account.address-form', ['address' => $this->own($request, $address)]);
    }

    public function update(Request $request, Address $address)
    {
        $this->own($request, $address)->update($request->validate($this->rules()) + ['is_default' => $request->boolean('is_default')]);

        return redirect()->route('account.addresses.index')->with('ok', __('Adresse mise à jour.'));
    }

    public function destroy(Request $request, Address $address)
    {
        $this->own($request, $address)->delete();

        return back()->with('ok', __('Adresse supprimée.'));
    }
}
