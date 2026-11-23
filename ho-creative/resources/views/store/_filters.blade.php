<aside class="filters">
  <form class="card" method="get">
    @if(isset($q))<input type="hidden" name="q" value="{{ $q }}">@endif
    <div class="field"><label for="f-sort">{{ __('Trier par') }}</label>
      <select id="f-sort" name="sort" onchange="this.form.submit()">
        @foreach(['' => __('Pertinence'), 'price_asc' => __('Prix croissant'), 'price_desc' => __('Prix décroissant'), 'name' => __('Nom'), 'new' => __('Nouveautés')] as $k => $v)
          <option value="{{ $k }}" @selected(request('sort') === $k)>{{ $v }}</option>
        @endforeach
      </select></div>
    <div class="field"><label for="f-mode">{{ __('Achat') }}</label>
      <select id="f-mode" name="mode" onchange="this.form.submit()">
        <option value="">{{ __('Tous') }}</option>
        <option value="instant" @selected(request('mode') === 'instant')>{{ __('Prix immédiat') }}</option>
        <option value="quote" @selected(request('mode') === 'quote')>{{ __('Sur devis') }}</option>
      </select></div>
    <div class="field"><label for="f-max">{{ __('Prix « à partir de » max. (DH)') }}</label>
      <input id="f-max" type="number" min="0" name="max" value="{{ request('max') }}"></div>
    <button class="btn btn-sm btn-block" type="submit">{{ __('Filtrer') }}</button>
  </form>
</aside>
