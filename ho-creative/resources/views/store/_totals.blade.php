<table class="small">
  <tr><td>{{ __('Sous-total HT') }}</td><td class="num">{{ money($t['subtotal']) }}</td></tr>
  @if((float) $t['discount_total'] > 0)<tr><td>{{ __('Remise') }}</td><td class="num">−{{ money($t['discount_total']) }}</td></tr>@endif
  <tr><td>{{ __('Livraison') }}</td><td class="num">{{ isset($shippingNote) ? $shippingNote : money($t['shipping_total']) }}</td></tr>
  @if((float) $t['tax_rate'] > 0)<tr><td>{{ setting_t('tax.label') }} ({{ rtrim(rtrim($t['tax_rate'], '0'), '.') }} %)</td><td class="num">{{ money($t['tax_total']) }}</td></tr>@endif
  <tr><td><strong>{{ __('Total TTC') }}</strong></td><td class="num"><strong style="font-size:1.25rem">{{ money($t['total']) }}</strong></td></tr>
</table>
