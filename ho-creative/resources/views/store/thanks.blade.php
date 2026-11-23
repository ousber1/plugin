@extends('layouts.store')
@section('title', __('Merci pour votre commande'))
@push('head')<meta name="robots" content="noindex">@endpush
@section('content')
<div class="container section" style="max-width:780px">
  <div class="card center">
    <h1>🎉 {{ __('Merci, commande :n reçue !', ['n' => $order->number]) }}</h1>
    <p>{{ __('Montant total :') }} <strong>{{ money($order->total) }}</strong> {{ __('TTC') }}</p>
    @if($order->payment_method === 'bank_transfer')
      <div class="alert alert-info" style="text-align:start">
        <strong>{{ __('Virement bancaire') }}</strong><br>
        {{ __('Banque') }} : {{ setting('company.bank_name') ?: '—' }}<br>RIB : {{ setting('company.rib') ?: __('communiqué par e-mail') }}<br>
        {{ __('Référence à indiquer') }} : <strong>{{ $order->number }}</strong>
      </div>
    @elseif($order->payment_method === 'cod')
      <p class="muted">{{ __('Vous réglerez à la livraison.') }}</p>
    @elseif($order->payment_method === 'in_store')
      <p class="muted">{{ __('Vous réglerez à l\'atelier lors du retrait.') }}</p>
    @endif
    <p>{{ __('Prochaine étape : envoyez vos fichiers si ce n\'est pas fait, puis validez le BAT que nous vous soumettrons.') }}</p>
    <a class="btn btn-accent" href="{{ $order->user_id ? route('account.orders.show', $order) : route('guest.order', $order->guest_token) }}">{{ __('Suivre ma commande') }}</a>
    @if(! $order->user_id)<p class="small muted" style="margin-top:10px">{{ __('Conservez ce lien de suivi privé, il vous a aussi été envoyé par e-mail.') }}</p>@endif
  </div>
</div>
@endsection
