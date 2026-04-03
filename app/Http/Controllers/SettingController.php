<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group');
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string',
            'receipt_logo' => 'nullable|image|max:2048',
        ]);

        // Map keys to their groups
        $groupMap = [
            'store_name' => 'general', 'currency' => 'general', 'store_phone' => 'general',
            'store_email' => 'general', 'store_address' => 'general', 'tax_rate' => 'general',
            'low_stock_threshold' => 'general', 'store_website' => 'general', 'store_city' => 'general',
            'default_language' => 'general', 'timezone' => 'general',
            'receipt_header' => 'receipt', 'receipt_footer' => 'receipt', 'receipt_width' => 'receipt',
            'receipt_show_logo' => 'receipt', 'receipt_logo' => 'receipt',
            'ice' => 'invoice', 'if_number' => 'invoice', 'rc' => 'invoice', 'cnss' => 'invoice',
            'patente' => 'invoice', 'bank_name' => 'invoice', 'bank_rib' => 'invoice',
            'invoice_conditions' => 'invoice', 'invoice_footer' => 'invoice',
            'invoice_template' => 'invoice', 'invoice_color' => 'invoice',
            'invoice_show_logo' => 'invoice', 'invoice_due_days' => 'invoice',
            'invoice_notes' => 'invoice', 'invoice_mention_legale' => 'invoice',
            'whatsapp_token' => 'whatsapp', 'whatsapp_phone_id' => 'whatsapp', 'whatsapp_verify_token' => 'whatsapp',
            'openai_api_key' => 'api', 'meta_ads_token' => 'api', 'meta_ads_account_id' => 'api',
            'pos_default_customer' => 'pos', 'pos_sound_enabled' => 'pos', 'pos_auto_print' => 'pos',
            'notification_low_stock' => 'notifications', 'notification_new_order' => 'notifications',
            'notification_email' => 'notifications',
        ];

        foreach ($request->input('settings', []) as $key => $value) {
            $group = $groupMap[$key] ?? 'general';
            Setting::set($key, $value ?? '', $group);
        }

        // Handle checkboxes (unchecked = not sent)
        $checkboxes = [
            'receipt_show_logo' => 'receipt',
            'invoice_show_logo' => 'invoice',
            'pos_sound_enabled' => 'pos',
            'pos_auto_print' => 'pos',
            'notification_low_stock' => 'notifications',
            'notification_new_order' => 'notifications',
        ];
        foreach ($checkboxes as $key => $group) {
            if (!$request->has("settings.{$key}")) {
                Setting::set($key, '0', $group);
            }
        }

        // Handle logo upload
        if ($request->hasFile('receipt_logo')) {
            $path = $request->file('receipt_logo')->store('receipts', 'public');
            Setting::set('receipt_logo', $path, 'receipt');
        }

        return redirect()->route('settings.index')->with('success', 'Settings updated successfully.');
    }

    public function profile()
    {
        $user = Auth::user();
        return view('settings.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'current_password' => 'nullable|required_with:new_password',
            'new_password' => 'nullable|min:8|confirmed',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;

        if (!empty($validated['current_password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.']);
            }
            $user->password = Hash::make($validated['new_password']);
        }

        $user->save();
        return redirect()->route('profile')->with('success', 'Profile updated.');
    }

    public function users()
    {
        $users = User::latest()->paginate(20);
        return view('settings.users', compact('users'));
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,staff',
            'phone' => 'nullable|string|max:20',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;

        User::create($validated);
        return redirect()->route('admin.users')->with('success', 'User created.');
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:admin,staff',
            'is_active' => 'boolean',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->input('password'));
        }

        $user->update($validated);
        return redirect()->route('admin.users')->with('success', 'User updated.');
    }

    public function deleteUser(int $id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users')->with('error', 'You cannot delete your own account.');
        }

        // Deactivate and reassign sales to admin before deleting
        $user->update(['is_active' => false]);

        // Check if user has sales - if so, just deactivate
        if ($user->sales()->count() > 0) {
            return redirect()->route('admin.users')->with('success', 'User deactivated (has sales history).');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
    }
}
