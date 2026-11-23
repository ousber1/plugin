@extends('layouts.store')
@section('title', __('Devis :n', ['n' => $quote->number]))
@push('head')<meta name="robots" content="noindex,nofollow">@endpush
@section('content')
<div class="container section" style="padding-top:20px;max-width:960px">
  <div class="flex between"><h1>{{ __('Devis :n', ['n' => $quote->number]) }}</h1><a class="btn btn-ghost btn-sm" href="{{ route('quote.public.pdf', $quote->token) }}" target="_blank">⬇ PDF</a></div>
  <p class="muted">{{ $quote->customer->displayName() }} · {{ __('Révision :r', ['r' => $rev->revision]) }} · {{ __('Valable jusqu\'au :d', ['d' => d($quote->expires_at)]) }}</p>
  <div class="card table-wrap">
    <table>
      <thead><tr><th>{{ __('Désignation') }}</th><th class="num">{{ __('Qté') }}</th><th class="num">{{ __('P.U. HT') }}</th><th class="num">{{ __('Total HT') }}</th></tr></thead>
      <tbody>
        @foreach($rev->items->sortBy('sort') as $i)
          <tr><td><strong>{{ $i->product?->t('name') ?: $i->description }}</strong>
            @if($i->options_snapshot)<div class="small muted">{{ collect([$i->options_snapshot['size'] ?? null])->merge(collect($i->options_snapshot['options'] ?? [])->map(fn ($o) => $o['label'].' : '.$o['value_label']))->filter()->implode(' · ') }}</div>@endif</td>
            <td class="num">{{ $i->quantity }}</td><td class="num">{{ money(round((float) $i->unit_price, 2)) }}</td><td class="num">{{ money($i->total) }}</td></tr>
        @endforeach
      </tbody>
    </table>
    <div style="max-width:360px;margin-inline-start:auto">@include('store._totals', ['t' => ['subtotal' => $rev->subtotal, 'discount_total' => $rev->discount_total, 'shipping_total' => $rev->shipping_total, 'tax_rate' => $rev->tax_rate, 'tax_total' => $rev->tax_total, 'total' => $rev->total]])</div>
    @if($rev->notes)<p class="small">{{ $rev->notes }}</p>@endif
  </div>
  @if($quote->canRespond())
    <form class="card" method="post" action="{{ route('quote.public.respond', $quote->token) }}" data-once>@csrf
      <input type="hidden" name="revision" value="{{ $quote->current_revision }}">
      <h2 style="font-size:1.15rem">{{ __('Votre réponse') }}</h2>
      <div class="grid-2">
        <div class="field"><label for="rname">{{ __('Nom du signataire') }} *</label><input id="rname" name="name" required value="{{ $quote->customer->name }}"></div>
        <div class="field"><label for="rnote">{{ __('Commentaire') }}</label><input id="rnote" name="note"></div>
      </div>
      <div class="flex">
        <button class="btn btn-accent" name="decision" value="accept">✔ {{ __('J\'accepte ce devis (bon pour accord)') }}</button>
        <button class="btn btn-ghost" name="decision" value="reject">{{ __('Je refuse') }}</button>
      </div>
    </form>
  @else
    <div class="alert alert-info">{{ __('Statut du devis :') }} <strong>{{ __('quote_status.'.$quote->status) }}</strong>@if($quote->responded_at) – {{ d($quote->responded_at, true) }} ({{ $quote->response_name }})@endif</div>
  @endif
</div>
@endsection
