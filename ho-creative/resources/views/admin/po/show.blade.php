@extends('layouts.admin')
@section('title', $po->number)
@section('content')
<div class="page-head"><h1>{{ $po->number }} <span class="pill">{{ __('po_status.'.$po->status) }}</span></h1>
  <div class="flex"><form method="post" action="{{ route('admin.po.document', $po) }}">@csrf<button class="btn btn-sm btn-ghost">PDF</button></form>
    @if(in_array($po->status, ['draft']))<form method="post" action="{{ route('admin.po.status', $po) }}">@csrf<button class="btn btn-sm" name="status" value="sent">Marquer envoyé</button></form>@endif
    @if(! in_array($po->status, ['received', 'cancelled']))<form method="post" action="{{ route('admin.po.status', $po) }}" data-confirm="Annuler ce bon ?">@csrf<button class="btn btn-sm btn-ghost" name="status" value="cancelled">Annuler</button></form>@endif</div></div>
<p>{{ $po->supplier->name }} · prévu {{ d($po->expected_at) }} · {{ $po->notes }}</p>
<form class="card table-wrap" method="post" action="{{ route('admin.po.receive', $po) }}" data-once>@csrf
  <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
  <table><thead><tr><th>Article</th><th class="num">Commandé</th><th class="num">Reçu</th><th class="num">Coût u. HT</th><th class="num">Total</th><th>Réception</th></tr></thead><tbody>
  @foreach($po->items as $i)@php($rest = (float) $i->quantity - (float) $i->quantity_received)
    <tr><td>{{ $i->description }} @if($i->job)<span class="small">→ {{ $i->job->number }}</span>@endif</td><td class="num">{{ (float) $i->quantity }} {{ $i->material?->unit }}</td><td class="num">{{ (float) $i->quantity_received }}</td><td class="num">{{ round((float) $i->unit_cost, 2) }}</td><td class="num">{{ money((float) $i->quantity * (float) $i->unit_cost) }}</td>
      <td>@if($rest > 0 && ! in_array($po->status, ['cancelled']))<input type="number" step="0.001" min="0" max="{{ $rest }}" name="qty[{{ $i->id }}]" placeholder="reste {{ $rest }}" style="width:120px">@else ✔ @endif</td></tr>
  @endforeach</tbody></table>
  <p class="right"><strong>Total HT : {{ money($po->total) }}</strong></p>
  @if(! in_array($po->status, ['received', 'cancelled']))<button class="btn btn-sm">Enregistrer la réception (partielle possible)</button>@endif
</form>
@if($docs->isNotEmpty())<div class="card">@foreach($docs as $d)<p class="small"><a href="{{ route('files.document', $d) }}" target="_blank">{{ $d->number }}</a> – {{ d($d->issued_at) }}</p>@endforeach</div>@endif
@endsection
