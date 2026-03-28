@extends('layouts.app')
@section('title', 'Manage Users')

@section('content')
<div class="max-w-4xl space-y-6" x-data="{ showForm: false }">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold">Manage Users</h2>
        </div>
        <button @click="showForm = !showForm" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700"><i class="fas fa-plus mr-1"></i> Add User</button>
    </div>

    <div x-show="showForm" x-cloak x-transition class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('admin.users.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div><label class="block text-xs font-medium mb-1">Name</label><input type="text" name="name" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-xs font-medium mb-1">Email</label><input type="email" name="email" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-xs font-medium mb-1">Password</label><input type="password" name="password" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-xs font-medium mb-1">Role</label><select name="role" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
            <div><label class="block text-xs font-medium mb-1">Phone</label><input type="text" name="phone" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div class="flex items-end"><button type="submit" class="w-full py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">Create User</button></div>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-3 text-left">Name</th><th class="px-6 py-3 text-left">Email</th><th class="px-6 py-3 text-center">Role</th><th class="px-6 py-3 text-center">Status</th><th class="px-6 py-3 text-left">Created</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($users as $u)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3 font-medium">{{ $u->name }}</td>
                    <td class="px-6 py-3 text-slate-400">{{ $u->email }}</td>
                    <td class="px-6 py-3 text-center"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $u->role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">{{ ucfirst($u->role ?? 'staff') }}</span></td>
                    <td class="px-6 py-3 text-center"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $u->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="px-6 py-3 text-xs text-slate-400">{{ $u->created_at->format('M d, Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
@endsection
