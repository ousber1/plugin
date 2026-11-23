@extends('layouts.admin')
@section('title', 'Demande de devis')
@section('content')
<div class="page-head"><h1>Demande de {{ $r->name }}</h1><a class="btn btn-sm btn-accent" href="{{ route('admin.quotes.create', ['request' => $r->id]) }}">Établir le devis</a></div>
<div class="card"><dl class="dl">
  <dt>Société</dt><dd>{{ $r->company }}</dd><dt>Contact</dt><dd>{{ $r->email }} · {{ $r->phone }}</dd><dt>Produit</dt><dd>{{ $r->product_type }}</dd>
  <dt>Dimensions</dt><dd>{{ $r->width ? (float) $r->width.' × '.(float) $r->height.' '.$r->unit : '—' }}</dd><dt>Quantité</dt><dd>{{ $r->quantity }}</dd>
  <dt>Matière</dt><dd>{{ $r->material }}</dd><dt>Finitions</dt><dd>{{ $r->finishes }}</dd><dt>Date souhaitée</dt><dd>{{ d($r->deadline) }}</dd><dt>Ville</dt><dd>{{ $r->city }}</dd>
  <dt>Détails</dt><dd style="white-space:pre-wrap">{{ $r->notes }}</dd>
  <dt>Pièces jointes</dt><dd>@forelse($r->attachments as $a)<a href="{{ route('files.attachment', $a) }}">{{ $a->original_name }}</a><br>@empty—@endforelse</dd>
  @if($r->quote)<dt>Devis</dt><dd><a href="{{ route('admin.quotes.show', $r->quote) }}">{{ $r->quote->number }}</a></dd>@endif
</dl>
@if($r->phone)<a class="btn btn-sm btn-wa" target="_blank" href="https://wa.me/{{ preg_replace('/\D/', '', $r->phone) }}">WhatsApp</a>@endif</div>
@endsection
