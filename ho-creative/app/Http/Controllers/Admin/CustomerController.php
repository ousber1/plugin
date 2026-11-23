<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Money;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    protected function rules(): array
    {
        return ['type' => 'required|in:individual,company', 'name' => 'required|string|max:120', 'company_name' => 'nullable|string|max:190',
            'email' => 'nullable|email|max:190', 'phone' => 'nullable|string|max:40', 'whatsapp' => 'nullable|string|max:40',
            'ice' => 'nullable|string|max:30', 'rc' => 'nullable|string|max:30', 'if_number' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:80', 'credit_limit' => 'nullable|numeric|min:0', 'notes' => 'nullable|string|max:5000',
            'source' => 'nullable|in:web,walk_in,phone,whatsapp,b2b,email'];
    }

    public function index(Request $request)
    {
        $this->authorize('customers.manage');
        $customers = Customer::withCount('orders')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('ice', 'like', "%$s%")))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest()->paginate(40)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function create()
    {
        $this->authorize('customers.manage');

        return view('admin.customers.form', ['customer' => new Customer(['type' => 'individual', 'source' => 'walk_in'])]);
    }

    public function store(Request $request)
    {
        $this->authorize('customers.manage');
        $c = Customer::create($request->validate($this->rules()));

        return redirect($request->input('return') ?: route('admin.customers.show', $c))->with('ok', 'Client créé.');
    }

    public function edit(Customer $customer)
    {
        $this->authorize('customers.manage');

        return view('admin.customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('customers.manage');
        $customer->update($request->validate($this->rules()));

        return redirect()->route('admin.customers.show', $customer)->with('ok', 'Client mis à jour.');
    }

    public function show(Customer $customer)
    {
        $this->authorize('customers.manage');
        $customer->load(['contacts', 'addresses', 'customerNotes.user', 'orders' => fn ($q) => $q->latest(), 'quotes' => fn ($q) => $q->latest()->with('currentRevision'), 'users']);
        $payments = $customer->payments()->latest()->limit(30)->get();
        $balance = Money::str(Money::sum($customer->orders->where('status', '!=', 'cancelled')->map(fn ($o) => $o->balance())));

        return view('admin.customers.show', compact('customer', 'payments', 'balance'));
    }

    public function addContact(Request $request, Customer $customer)
    {
        $this->authorize('customers.manage');
        $customer->contacts()->create($request->validate(['name' => 'required|max:120', 'position' => 'nullable|max:120', 'email' => 'nullable|email', 'phone' => 'nullable|max:40']));

        return back()->with('ok', 'Contact ajouté.');
    }

    public function addNote(Request $request, Customer $customer)
    {
        $this->authorize('customers.manage');
        $customer->customerNotes()->create($request->validate(['body' => 'required|max:5000']) + ['user_id' => auth()->id()]);

        return back()->with('ok', 'Note ajoutée.');
    }

    public function addAddress(Request $request, Customer $customer)
    {
        $this->authorize('customers.manage');
        $customer->addresses()->create($request->validate(['label' => 'nullable|max:60', 'type' => 'required|in:shipping,billing', 'name' => 'required|max:120', 'company' => 'nullable|max:190',
            'line1' => 'required|max:190', 'line2' => 'nullable|max:190', 'city' => 'required|max:80', 'postal_code' => 'nullable|max:20', 'phone' => 'nullable|max:40']));

        return back()->with('ok', 'Adresse ajoutée.');
    }
}
