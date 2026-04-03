@extends('layouts.app')
@section('title', 'Add Customer')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('customers.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-xl font-bold">Add Customer</h2>
    </div>

    <form method="POST" action="{{ route('customers.store') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">
        @csrf
        @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
            <ul class="list-disc list-inside text-sm text-red-600">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium mb-1">Name *</label><input type="text" name="name" value="{{ old('name') }}" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-sm font-medium mb-1">Phone</label><input type="text" name="phone" value="{{ old('phone') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-sm font-medium mb-1">Email</label><input type="email" name="email" value="{{ old('email') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-sm font-medium mb-1">City</label><input type="text" name="city" value="{{ old('city') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div class="col-span-2"><label class="block text-sm font-medium mb-1">Address</label><textarea name="address" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">{{ old('address') }}</textarea></div>
            <div class="col-span-2"><label class="block text-sm font-medium mb-1">Tags (comma-separated)</label><input type="text" name="tags" value="{{ old('tags') }}" placeholder="VIP, frequent, wholesale" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div class="col-span-2"><label class="block text-sm font-medium mb-1">Notes</label><textarea name="notes" rows="3" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">{{ old('notes') }}</textarea></div>
        </div>

        <div class="flex gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
            <a href="{{ route('customers.index') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg text-sm font-bold hover:bg-primary-700"><i class="fas fa-save mr-1"></i> Save Customer</button>
        </div>
    </form>
</div>
@endsection
