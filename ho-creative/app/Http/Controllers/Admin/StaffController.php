<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index()
    {
        $this->authorize('staff.manage');

        return view('admin.staff.index', ['users' => User::where('is_staff', true)->with('roles')->orderBy('name')->get(), 'roles' => Role::with('permissions')->get()]);
    }

    public function create()
    {
        $this->authorize('staff.manage');

        return view('admin.staff.form', ['user' => new User(['is_active' => true]), 'roles' => Role::all()]);
    }

    public function store(Request $request)
    {
        $this->authorize('staff.manage');
        $v = $request->validate(['name' => 'required|max:120', 'email' => 'required|email|unique:users,email', 'phone' => 'nullable|max:40',
            'password' => ['required', Password::min(10)->letters()->numbers()], 'roles' => 'required|array|min:1', 'roles.*' => 'exists:roles,name']);
        $this->guardOwner($request, $v['roles']);
        $u = User::create($v + ['locale' => 'fr']);
        $u->forceFill(['is_staff' => true, 'is_active' => true])->save();
        $u->syncRoles($v['roles']);
        AuditLogger::log('staff.created', $u, null, ['roles' => $v['roles']]);

        return redirect()->route('admin.staff.index')->with('ok', 'Membre de l\'équipe créé.');
    }

    public function edit(User $user)
    {
        $this->authorize('staff.manage');
        abort_unless($user->is_staff, 404);

        return view('admin.staff.form', ['user' => $user, 'roles' => Role::all()]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('staff.manage');
        abort_unless($user->is_staff, 404);
        $v = $request->validate(['name' => 'required|max:120', 'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)], 'phone' => 'nullable|max:40',
            'password' => ['nullable', Password::min(10)->letters()->numbers()], 'roles' => 'required|array|min:1', 'roles.*' => 'exists:roles,name', 'is_active' => 'boolean']);
        $this->guardOwner($request, $v['roles']);
        $oldRoles = $user->getRoleNames()->all();
        if ($user->id === $request->user()->id && ! in_array('owner', $v['roles']) && in_array('owner', $oldRoles)) {
            return back()->with('err', 'Vous ne pouvez pas retirer votre propre rôle propriétaire.');
        }
        if (in_array('owner', $oldRoles) && ! in_array('owner', $v['roles']) && User::role('owner')->where('is_active', true)->count() <= 1) {
            return back()->with('err', 'Il doit rester au moins un propriétaire actif.');
        }
        $user->fill(collect($v)->only(['name', 'email', 'phone'])->all());
        if (! empty($v['password'])) {
            $user->password = $v['password'];
        }
        $user->is_active = $request->boolean('is_active') || $user->id === $request->user()->id;
        $user->save();
        $user->syncRoles($v['roles']);
        if ($oldRoles !== $v['roles']) {
            AuditLogger::log('staff.roles_changed', $user, ['roles' => $oldRoles], ['roles' => $v['roles']]);
        }

        return redirect()->route('admin.staff.index')->with('ok', 'Membre mis à jour.');
    }

    protected function guardOwner(Request $r, array $roles): void
    {
        if (in_array('owner', $roles) && ! $r->user()->hasRole('owner')) {
            abort(403, 'Seul un propriétaire peut attribuer le rôle propriétaire.');
        }
    }
}
