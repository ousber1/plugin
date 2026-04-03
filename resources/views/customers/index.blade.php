@extends('layouts.app')
@section('title', \App\Helpers\Lang::t('customers.title'))

@php $L = \App\Helpers\Lang::class; $cur = \App\Models\Setting::get('currency') ?? 'DH'; @endphp

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold">{{ $L::t('customers.title') }}</h2>
        <a href="{{ route('customers.create') }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            <i class="fas fa-plus mr-1"></i> {{ $L::t('customers.add') }}
        </a>
    </div>

    <form method="GET" class="flex flex-wrap gap-3 bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ $L::t('common.search') }}..." class="flex-1 min-w-[200px] border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="text" name="tag" value="{{ request('tag') }}" placeholder="{{ $L::locale() === 'fr' ? 'Filtrer par tag...' : 'Filter by tag...' }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <button type="submit" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300"><i class="fas fa-filter mr-1"></i> {{ $L::t('common.filter') }}</button>
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500">
                <tr>
                    <th class="px-6 py-3 text-left">{{ $L::t('customers.name') }}</th>
                    <th class="px-6 py-3 text-left">{{ $L::t('customers.email') }}</th>
                    <th class="px-6 py-3 text-left">{{ $L::t('customers.phone') }}</th>
                    <th class="px-6 py-3 text-left">{{ $L::t('customers.city') }}</th>
                    <th class="px-6 py-3 text-left">Tags</th>
                    <th class="px-6 py-3 text-right">{{ $L::t('customers.total_purchases') }}</th>
                    <th class="px-6 py-3 text-right">{{ $L::t('customers.total_spent') }}</th>
                    <th class="px-6 py-3 text-center">{{ $L::t('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($customers as $customer)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3 font-medium">{{ $customer->name }}</td>
                    <td class="px-6 py-3 text-slate-400">{{ $customer->email ?? '-' }}</td>
                    <td class="px-6 py-3">{{ $customer->phone ?? '-' }}</td>
                    <td class="px-6 py-3">{{ $customer->city ?? '-' }}</td>
                    <td class="px-6 py-3">
                        @foreach(($customer->tags ?? []) as $tag)
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 mr-1">{{ $tag }}</span>
                        @endforeach
                    </td>
                    <td class="px-6 py-3 text-right">{{ $customer->total_purchases }}</td>
                    <td class="px-6 py-3 text-right font-semibold">{{ number_format($customer->total_spent, 2) }} {{ $cur }}</td>
                    <td class="px-6 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('customers.show', $customer) }}" class="text-primary-600 hover:text-primary-800" title="{{ $L::t('common.view') }}"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('customers.edit', $customer) }}" class="text-slate-400 hover:text-slate-600" title="{{ $L::t('common.edit') }}"><i class="fas fa-edit"></i></a>
                            <button onclick="confirmDeleteCustomer({{ $customer->id }}, '{{ addslashes($customer->name) }}')" class="text-red-400 hover:text-red-600" title="{{ $L::locale() === 'fr' ? 'Supprimer' : 'Delete' }}"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">{{ $L::locale() === 'fr' ? 'Aucun client trouvé' : 'No customers found' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->withQueryString()->links() }}
</div>

{{-- Delete Confirmation Modal --}}
<div id="deleteModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <div class="text-center">
            <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold mb-2">{{ $L::locale() === 'fr' ? 'Supprimer le client' : 'Delete Customer' }}</h3>
            <p class="text-sm text-slate-500 mb-4">{{ $L::locale() === 'fr' ? 'Voulez-vous vraiment supprimer' : 'Are you sure you want to delete' }} <strong id="deleteCustomerName"></strong>?</p>
        </div>
        <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" class="flex-1 px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium">{{ $L::locale() === 'fr' ? 'Annuler' : 'Cancel' }}</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">{{ $L::locale() === 'fr' ? 'Supprimer' : 'Delete' }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDeleteCustomer(id, name) {
    document.getElementById('deleteCustomerName').textContent = name;
    document.getElementById('deleteForm').action = '/customers/' + id;
    document.getElementById('deleteModal').style.display = 'flex';
}
</script>
@endsection
