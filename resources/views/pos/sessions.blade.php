@extends('layouts.app')
@section('title', 'Register Sessions')

@section('content')
<div class="space-y-6" x-data="{ showOpen: false, showClose: false, closeId: null }">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold">Register Sessions</h2>
            <p class="text-sm text-slate-500">Manage daily register opening and closing</p>
        </div>
        <button @click="showOpen = true" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            <i class="fas fa-play mr-1"></i> Open Session
        </button>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-6 py-3 text-left">User</th>
                    <th class="px-6 py-3 text-left">Opened At</th>
                    <th class="px-6 py-3 text-right">Opening Amount</th>
                    <th class="px-6 py-3 text-right">Closing Amount</th>
                    <th class="px-6 py-3 text-right">Difference</th>
                    <th class="px-6 py-3 text-center">Status</th>
                    <th class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($sessions as $session)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3">{{ $session->user->name ?? 'N/A' }}</td>
                    <td class="px-6 py-3">{{ $session->opened_at?->format('M d, Y H:i') }}</td>
                    <td class="px-6 py-3 text-right">${{ number_format($session->opening_amount, 2) }}</td>
                    <td class="px-6 py-3 text-right">{{ $session->closing_amount !== null ? '$' . number_format($session->closing_amount, 2) : '-' }}</td>
                    <td class="px-6 py-3 text-right {{ ($session->difference ?? 0) < 0 ? 'text-red-500' : 'text-emerald-500' }}">
                        {{ $session->difference !== null ? '$' . number_format($session->difference, 2) : '-' }}
                    </td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $session->status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ ucfirst($session->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        @if($session->status === 'open')
                        <button @click="closeId = {{ $session->id }}; showClose = true" class="text-primary-600 hover:underline text-xs font-medium">Close</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-6 py-8 text-center text-slate-400">No sessions found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Open Session Modal --}}
    <div x-show="showOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showOpen = false">
        <form method="POST" action="{{ route('pos.sessions.open') }}" class="bg-white dark:bg-slate-800 rounded-xl p-6 w-full max-w-md" x-transition>
            @csrf
            <h3 class="text-lg font-bold mb-4">Open Register Session</h3>
            <label class="block text-sm font-medium mb-1">Opening Amount ($)</label>
            <input type="number" name="opening_amount" step="0.01" min="0" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent mb-4">
            <div class="flex gap-3 justify-end">
                <button type="button" @click="showOpen = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">Open Session</button>
            </div>
        </form>
    </div>

    {{-- Close Session Modal --}}
    <div x-show="showClose" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showClose = false">
        <form :action="`{{ url('pos/sessions') }}/${closeId}/close`" method="POST" class="bg-white dark:bg-slate-800 rounded-xl p-6 w-full max-w-md" x-transition>
            @csrf
            <h3 class="text-lg font-bold mb-4">Close Register Session</h3>
            <label class="block text-sm font-medium mb-1">Closing Amount ($)</label>
            <input type="number" name="closing_amount" step="0.01" min="0" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent mb-2">
            <label class="block text-sm font-medium mb-1 mt-3">Notes</label>
            <textarea name="notes" rows="2" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent mb-4"></textarea>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="showClose = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium">Close Session</button>
            </div>
        </form>
    </div>
</div>
@endsection
