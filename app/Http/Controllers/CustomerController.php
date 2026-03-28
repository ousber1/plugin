<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List customers with search and tag filter.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tag')) {
            $tag = $request->input('tag');
            $query->whereJsonContains('tags', $tag);
        }

        $customers = $query->orderBy('name')->paginate(20)->withQueryString();

        // Gather distinct tags for the filter dropdown
        $allTags = Customer::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return view('customers.index', compact('customers', 'allTags'));
    }

    /**
     * Show create customer form.
     */
    public function create()
    {
        return view('customers.create');
    }

    /**
     * Store a new customer with validation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        try {
            $customer = Customer::create($validated);

            return redirect()->route('customers.show', $customer->id)
                ->with('success', 'Customer created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create customer: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show customer details with purchase history and messages.
     */
    public function show(int $id)
    {
        $customer = Customer::with(['whatsappContact'])->findOrFail($id);

        // Purchase history with eager loading
        $purchases = $customer->sales()
            ->with(['items.product:id,name,sku,image', 'payments'])
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'purchases_page');

        // Purchase summary aggregates
        $purchaseSummary = [
            'total_orders' => $customer->sales()->count(),
            'total_spent' => $customer->sales()->where('payment_status', '!=', 'unpaid')->sum('total'),
            'avg_order_value' => $customer->sales()->where('payment_status', '!=', 'unpaid')->avg('total') ?? 0,
        ];

        // WhatsApp messages if contact exists
        $messages = collect();
        if ($customer->whatsappContact) {
            $messages = $customer->whatsappContact
                ->conversations()
                ->with(['messages' => function ($q) {
                    $q->orderByDesc('created_at')->limit(20);
                }])
                ->get()
                ->pluck('messages')
                ->flatten()
                ->sortByDesc('created_at')
                ->take(20);
        }

        return view('customers.show', compact('customer', 'purchases', 'purchaseSummary', 'messages'));
    }

    /**
     * Show edit customer form.
     */
    public function edit(int $id)
    {
        $customer = Customer::findOrFail($id);

        return view('customers.edit', compact('customer'));
    }

    /**
     * Update a customer.
     */
    public function update(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        try {
            $customer->update($validated);

            return redirect()->route('customers.show', $customer->id)
                ->with('success', 'Customer updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update customer: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Soft delete a customer.
     */
    public function destroy(int $id)
    {
        try {
            $customer = Customer::findOrFail($id);
            $customer->delete();

            return redirect()->route('customers.index')
                ->with('success', 'Customer deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete customer: ' . $e->getMessage()]);
        }
    }
}
