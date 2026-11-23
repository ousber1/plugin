<div class="table-wrap"><table>
  <thead><tr><th>{{ __('Commande') }}</th><th>{{ __('Date') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Paiement') }}</th><th class="num">{{ __('Total TTC') }}</th></tr></thead>
  <tbody>
  @forelse($orders as $o)
    <tr><td><a href="{{ route('account.orders.show', $o) }}"><strong>{{ $o->number }}</strong></a></td><td>{{ d($o->placed_at) }}</td>
      <td><span class="pill">{{ __('public_status.'.$o->publicStatus()) }}</span></td><td><span class="pill">{{ __('payment_status.'.$o->payment_status) }}</span></td><td class="num">{{ money($o->total) }}</td></tr>
  @empty
    <tr><td colspan="5" class="muted">{{ __('Aucune commande pour le moment.') }}</td></tr>
  @endforelse
  </tbody>
</table></div>
