@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold">Notifications</h2>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
            <button type="submit" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300"><i class="fas fa-check-double mr-1"></i> Mark All Read</button>
        </form>
    </div>

    <div class="space-y-2">
        @forelse($notifications as $notification)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 flex items-start gap-4 {{ !$notification->is_read ? 'border-l-4 border-l-primary-500' : '' }}">
            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0
                @switch($notification->type)
                    @case('low_stock') bg-amber-100 dark:bg-amber-900/30 @break
                    @case('bad_ad') bg-red-100 dark:bg-red-900/30 @break
                    @case('high_roi') bg-emerald-100 dark:bg-emerald-900/30 @break
                    @default bg-blue-100 dark:bg-blue-900/30
                @endswitch
            ">
                @switch($notification->type)
                    @case('low_stock') <i class="fas fa-exclamation-triangle text-amber-600"></i> @break
                    @case('bad_ad') <i class="fas fa-chart-line text-red-600"></i> @break
                    @case('high_roi') <i class="fas fa-trophy text-emerald-600"></i> @break
                    @default <i class="fas fa-bell text-blue-600"></i>
                @endswitch
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium">{{ $notification->title }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ $notification->message }}</p>
                <p class="text-[10px] text-slate-300 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
            @if(!$notification->is_read)
            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf
                <button type="submit" class="text-xs text-primary-500 hover:underline">Mark read</button>
            </form>
            @endif
        </div>
        @empty
        <div class="text-center py-12 text-slate-400">
            <i class="fas fa-bell-slash text-4xl mb-3"></i>
            <p>No notifications</p>
        </div>
        @endforelse
    </div>
    {{ $notifications->links() }}
</div>
@endsection
