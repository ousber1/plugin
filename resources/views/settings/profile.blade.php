@extends('layouts.app')
@section('title', 'Profile')

@section('content')
<div class="max-w-2xl space-y-6">
    <h2 class="text-xl font-bold">My Profile</h2>

    <form method="POST" action="{{ route('profile.update') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">
        @csrf
        @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 rounded-lg p-3">
            <ul class="list-disc list-inside text-sm text-red-600">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <div class="flex items-center gap-4 mb-4">
            <div class="w-16 h-16 rounded-full bg-primary-500 flex items-center justify-center text-white text-2xl font-bold">
                {{ substr($user->name, 0, 1) }}
            </div>
            <div>
                <p class="font-semibold text-lg">{{ $user->name }}</p>
                <p class="text-sm text-slate-400">{{ ucfirst($user->role ?? 'staff') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium mb-1">Name</label><input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-sm font-medium mb-1">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div class="col-span-2"><label class="block text-sm font-medium mb-1">Phone</label><input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
        </div>

        <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
            <h4 class="text-sm font-semibold mb-3">Change Password (optional)</h4>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium mb-1">Current Password</label><input type="password" name="current_password" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div></div>
                <div><label class="block text-sm font-medium mb-1">New Password</label><input type="password" name="new_password" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-sm font-medium mb-1">Confirm Password</label><input type="password" name="new_password_confirmation" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            </div>
        </div>

        <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg text-sm font-bold hover:bg-primary-700"><i class="fas fa-save mr-1"></i> Update Profile</button>
    </form>
</div>
@endsection
