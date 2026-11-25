<?php /** Formulaire de devis. Variables : $doc, $items, $customer, $taxes, $units */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= $doc ? 'Devis ' . e($doc['number']) : 'Nouveau devis' ?></h1>
    <p class="text-body-secondary mb-0">Les prix catalogue tiennent compte du tarif du client (prix négocié, liste de prix, remise de groupe).</p></div>
</div>
<?php \App\Core\View::partial('doc_form', [
    'kind' => 'quotation', 'doc' => $doc, 'items' => $items, 'customer' => $customer, 'taxes' => $taxes, 'units' => $units,
    'action' => url($doc ? 'quotations/' . $doc['id'] : 'quotations'), 'cancelUrl' => url($doc ? 'quotations/' . $doc['id'] : 'quotations'),
]); ?>
