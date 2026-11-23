@extends('layouts.admin')
@section('title', 'Documents')
@section('content')
<h1>Factures & documents</h1>
<form class="toolbar"><div class="field"><input name="q" value="{{ request('q') }}" placeholder="Numéro"></div><div class="field"><select name="type"><option value="">Type</option>@foreach(\App\Models\Document::TYPES as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ __('doc.'.$t) }}</option>@endforeach</select></div><button class="btn btn-sm btn-ghost">Filtrer</button></form>
<div class="card table-wrap"><table><thead><tr><th>Numéro</th><th>Type</th><th>Date</th><th>Client</th><th>Commande</th><th class="num">HT</th><th class="num">TVA</th><th class="num">TTC</th></tr></thead><tbody>
@foreach($docs as $d)<tr><td><a href="{{ route('files.document', $d) }}" target="_blank">{{ $d->number }}</a></td><td>{{ __('doc.'.$d->type) }}</td><td>{{ d($d->issued_at) }}</td><td>{{ $d->customer?->displayName() ?? ($d->snapshot['customer']['name'] ?? '') }}</td><td>@if($d->order)<a href="{{ route('admin.orders.show', $d->order) }}">{{ $d->order->number }}</a>@endif</td><td class="num">{{ money($d->subtotal) }}</td><td class="num">{{ money($d->tax_total) }}</td><td class="num">{{ money($d->total) }}</td></tr>@endforeach
</tbody></table>{{ $docs->links('partials.pagination') }}</div>
<p class="small muted">Les documents émis sont figés (contenu enregistré à l'émission). Toute correction d'une facture passe par un avoir.</p>
@endsection
