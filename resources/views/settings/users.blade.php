@extends('layouts.app')
@section('title', \App\Helpers\Lang::locale() === 'fr' ? 'Gestion des utilisateurs' : 'Manage Users')

@php $L = \App\Helpers\Lang::class; $isFr = $L::locale() === 'fr'; @endphp

@section('content')
<div class="max-w-5xl space-y-6" x-data="{ showForm: false, editUser: null }">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold"><i class="fas fa-users-cog mr-2 text-purple-500"></i>{{ $isFr ? 'Gestion des utilisateurs' : 'Manage Users' }}</h2>
        </div>
        <button @click="showForm = !showForm; editUser = null" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            <i class="fas fa-plus mr-1"></i> {{ $isFr ? 'Ajouter un utilisateur' : 'Add User' }}
        </button>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-3 text-sm text-emerald-700 dark:text-emerald-400">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 text-sm text-red-700 dark:text-red-400">
        <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
    </div>
    @endif

    {{-- Create User Form --}}
    <div x-show="showForm && !editUser" x-cloak x-transition class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-sm font-semibold mb-4 border-b border-slate-200 dark:border-slate-700 pb-2">
            <i class="fas fa-user-plus mr-1 text-primary-500"></i> {{ $isFr ? 'Nouvel utilisateur' : 'New User' }}
        </h3>
        <form method="POST" action="{{ route('admin.users.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-medium mb-1">{{ $isFr ? 'Nom' : 'Name' }} *</label>
                <input type="text" name="name" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Email *</label>
                <input type="email" name="email" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">{{ $isFr ? 'Mot de passe' : 'Password' }} *</label>
                <input type="password" name="password" required minlength="8" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">{{ $isFr ? 'Role' : 'Role' }}</label>
                <select name="role" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">{{ $isFr ? 'Telephone' : 'Phone' }}</label>
                <input type="text" name="phone" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                    <i class="fas fa-save mr-1"></i> {{ $isFr ? 'Creer' : 'Create' }}
                </button>
                <button type="button" @click="showForm = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium">
                    {{ $isFr ? 'Annuler' : 'Cancel' }}
                </button>
            </div>
        </form>
    </div>

    {{-- Users Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500">
                <tr>
                    <th class="px-6 py-3 text-left">{{ $isFr ? 'Nom' : 'Name' }}</th>
                    <th class="px-6 py-3 text-left">Email</th>
                    <th class="px-6 py-3 text-left">{{ $isFr ? 'Telephone' : 'Phone' }}</th>
                    <th class="px-6 py-3 text-center">{{ $isFr ? 'Role' : 'Role' }}</th>
                    <th class="px-6 py-3 text-center">{{ $isFr ? 'Statut' : 'Status' }}</th>
                    <th class="px-6 py-3 text-left">{{ $isFr ? 'Cree le' : 'Created' }}</th>
                    <th class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($users as $u)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3 font-medium">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-xs font-bold text-primary-600">
                                {{ strtoupper(substr($u->name, 0, 1)) }}
                            </div>
                            {{ $u->name }}
                            @if($u->id === auth()->id())
                            <span class="text-xs text-slate-400">({{ $isFr ? 'vous' : 'you' }})</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-3 text-slate-400">{{ $u->email }}</td>
                    <td class="px-6 py-3 text-slate-400">{{ $u->phone ?? '-' }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $u->role === 'admin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' }}">
                            {{ ucfirst($u->role ?? 'staff') }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $u->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                            {{ $u->is_active ? ($isFr ? 'Actif' : 'Active') : ($isFr ? 'Inactif' : 'Inactive') }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-xs text-slate-400">{{ $u->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            {{-- Edit Button - inline form via JS --}}
                            <button onclick="openEditUser({{ json_encode(['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role ?? 'staff', 'phone' => $u->phone, 'is_active' => $u->is_active]) }})" class="text-slate-400 hover:text-slate-600" title="{{ $isFr ? 'Modifier' : 'Edit' }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            {{-- Delete Button (not for self) --}}
                            @if($u->id !== auth()->id())
                            <button onclick="confirmDeleteUser({{ $u->id }}, '{{ addslashes($u->name) }}')" class="text-red-400 hover:text-red-600" title="{{ $isFr ? 'Supprimer' : 'Delete' }}">
                                <i class="fas fa-trash"></i>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>

{{-- Edit User Modal --}}
<div id="editUserModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-sm font-semibold mb-4 border-b border-slate-200 dark:border-slate-700 pb-2">
            <i class="fas fa-user-edit mr-1 text-primary-500"></i> {{ $isFr ? 'Modifier l\'utilisateur' : 'Edit User' }}
        </h3>
        <form id="editUserForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-medium mb-1">{{ $isFr ? 'Nom' : 'Name' }} *</label>
                <input type="text" name="name" id="editName" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Email *</label>
                <input type="email" name="email" id="editEmail" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1">{{ $isFr ? 'Role' : 'Role' }}</label>
                    <select name="role" id="editRole" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">{{ $isFr ? 'Telephone' : 'Phone' }}</label>
                    <input type="text" name="phone" id="editPhone" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">{{ $isFr ? 'Nouveau mot de passe' : 'New Password' }} <span class="text-slate-400">({{ $isFr ? 'laisser vide pour ne pas changer' : 'leave empty to keep current' }})</span></label>
                <input type="password" name="password" minlength="8" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            </div>
            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="editActive" value="1" class="w-4 h-4 rounded border-slate-300 text-primary-600">
                    <span class="text-sm font-medium">{{ $isFr ? 'Compte actif' : 'Account Active' }}</span>
                </label>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('editUserModal').style.display='none'" class="flex-1 px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium">{{ $isFr ? 'Annuler' : 'Cancel' }}</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                    <i class="fas fa-save mr-1"></i> {{ $isFr ? 'Enregistrer' : 'Save' }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Delete User Modal --}}
<div id="deleteUserModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <div class="text-center">
            <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-user-times text-red-500 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold mb-2">{{ $isFr ? 'Supprimer l\'utilisateur' : 'Delete User' }}</h3>
            <p class="text-sm text-slate-500 mb-4">{{ $isFr ? 'Voulez-vous vraiment supprimer' : 'Are you sure you want to delete' }} <strong id="deleteUserName"></strong>?</p>
            <p class="text-xs text-red-500 mb-4">{{ $isFr ? 'Cette action est irreversible.' : 'This action cannot be undone.' }}</p>
        </div>
        <form id="deleteUserForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('deleteUserModal').style.display='none'" class="flex-1 px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium">{{ $isFr ? 'Annuler' : 'Cancel' }}</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">{{ $isFr ? 'Supprimer' : 'Delete' }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUser(user) {
    document.getElementById('editUserForm').action = '/admin/users/' + user.id;
    document.getElementById('editName').value = user.name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editRole').value = user.role;
    document.getElementById('editPhone').value = user.phone || '';
    document.getElementById('editActive').checked = user.is_active;
    document.getElementById('editUserModal').style.display = 'flex';
}
function confirmDeleteUser(id, name) {
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteUserForm').action = '/admin/users/' + id;
    document.getElementById('deleteUserModal').style.display = 'flex';
}
</script>
@endsection
