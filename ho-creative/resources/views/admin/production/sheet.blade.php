<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Fiche {{ $job->number }}</title><link rel="stylesheet" href="/assets/app.css">
<style>body{background:#fff}.print-sheet h1{font-size:1.4rem}.print-sheet td,.print-sheet th{border:1px solid #ccc}.box{border:2px solid #1B1F3B;border-radius:10px;padding:10px;margin-top:10px}</style></head>
<body><div class="print-sheet">
  <div class="flex between"><h1>FICHE DE PRODUCTION – {{ $job->number }}</h1><button class="btn btn-sm no-print" onclick="print()">Imprimer</button></div>
  <table>
    <tr><th>Commande</th><td>{{ $job->order->number }}</td><th>Client</th><td>{{ $job->order->customer?->displayName() }}</td></tr>
    <tr><th>Échéance</th><td><strong>{{ d($job->due_at) }}</strong></td><th>Priorité</th><td>{{ strtoupper($job->priority) }}</td></tr>
    <tr><th>Atelier</th><td>{{ $job->department }}</td><th>Machine</th><td>{{ $job->machine?->name }}</td></tr>
    <tr><th>Opérateur</th><td>{{ $job->assignee?->name }}</td><th>Livraison</th><td>{{ $job->order->shipping_method === 'pickup' ? 'Retrait atelier' : 'Livraison – '.($job->order->shipping_address['city'] ?? '') }}</td></tr>
  </table>
  <div class="box"><h2 style="font-size:1.1rem">{{ $job->item->product?->t('name', 'fr') ?: $job->item->description }}</h2>
    <table>
      <tr><th>Quantité</th><td style="font-size:1.3rem"><strong>{{ $job->item->quantity }}</strong></td></tr>
      @if($job->item->width_mm)<tr><th>Format fini</th><td>{{ (float) $job->item->width_mm }} × {{ (float) $job->item->height_mm }} mm (+ 3 mm fond perdu)</td></tr>@endif
      @if(! empty($job->item->breakdown['area_m2']))<tr><th>Surface totale</th><td>{{ $job->item->breakdown['area_m2'] }} m²</td></tr>@endif
      @foreach($job->item->options_snapshot['options'] ?? [] as $o)<tr><th>{{ $o['label'] }}</th><td>{{ $o['value_label'] }}</td></tr>@endforeach
    </table></div>
  <div class="box"><strong>Fichier approuvé :</strong>
    @if($approved) BAT v{{ $approved->version }} approuvé le {{ d($approved->responded_at, true) }} ({{ $approved->original_name }})
    @elseif(! $job->item->requires_proof) BAT non requis – dernier fichier : {{ optional($job->item->artworkFiles->sortByDesc('version')->first())->original_name ?? ($job->item->design ? 'design en ligne' : '—') }}
    @else <span class="error-text">AUCUN BAT APPROUVÉ – NE PAS IMPRIMER</span>@endif
  </div>
  <div class="box"><strong>Notes internes :</strong><p style="white-space:pre-wrap">{{ $job->internal_notes }}</p>@if($job->item->notes)<p><strong>Note client :</strong> {{ $job->item->notes }}</p>@endif</div>
  <div class="box"><strong>Contrôle qualité :</strong> ☐ Couleurs ☐ Format / coupe ☐ Finitions ☐ Quantité ☐ Emballage — Visa : ____________</div>
</div></body></html>
