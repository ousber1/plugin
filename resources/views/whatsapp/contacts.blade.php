@extends('layouts.app')
@section('title', 'WhatsApp Contacts')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('whatsapp.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold">WhatsApp Contacts</h2>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500"><tr><th class="px-6 py-3 text-left">Name</th><th class="px-6 py-3 text-left">Phone</th><th class="px-6 py-3 text-left">Customer</th><th class="px-6 py-3 text-center">Subscribed</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($contacts as $contact)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3 font-medium">{{ $contact->name ?? 'Unknown' }}</td>
                    <td class="px-6 py-3">{{ $contact->phone }}</td>
                    <td class="px-6 py-3">{{ $contact->customer->name ?? '-' }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $contact->is_subscribed ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                            {{ $contact->is_subscribed ? 'Yes' : 'No' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">No contacts yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
