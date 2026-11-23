@extends('layouts.store')
@section('content')
<div class="container section" style="max-width:560px">
  <div class="card">
    <span class="pill pill-demo">TEST</span>
    <h1 style="font-size:1.4rem">Passerelle de test (hors production)</h1>
    <p>Commande {{ $order->number }} – solde {{ money($order->balance()) }}</p>
    <p class="small muted">Simule un rappel serveur signé du prestataire. Désactivé quand APP_ENV=production.</p>
    <form method="post" action="{{ route('payment.sandbox.pay', $order) }}" class="flex">@csrf
      <button class="btn btn-accent" name="status" value="paid">Simuler un paiement accepté</button>
      <button class="btn btn-ghost" name="status" value="fail">Simuler un refus</button>
    </form>
  </div>
</div>
@endsection
