<!doctype html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ __('Redirection vers le paiement sécurisé') }}</title><link rel="stylesheet" href="/assets/app.css"></head>
<body><div class="container section center">
<p>{{ __('Redirection vers la page de paiement sécurisée de la banque…') }}</p>
<form id="pay" method="post" action="{{ $form['action'] }}">
  @foreach($form['fields'] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
  @if(str_starts_with($form['action'], url('/')))@csrf @endif
  <button class="btn btn-accent">{{ __('Continuer') }}</button>
</form>
<script>document.getElementById('pay').submit()</script>
</div></body></html>
