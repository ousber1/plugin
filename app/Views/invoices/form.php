<?php /** Formulaire de facture (brouillon). Variables : $doc, $items, $customer, $taxes, $units */ ?>
<div class="hoc-page-head">
  <div><h1 class="h3 fw-bold mb-1"><?= $doc ? 'Facture brouillon' : 'Nouvelle facture' ?><?= $doc && $doc['invoice_type'] !== 'standard' ? ' — ' . e(\App\Services\InvoiceService::TYPES[$doc['invoice_type']]) : '' ?></h1>
    <p class="text-body-secondary mb-0">La facture reste un brouillon modifiable jusqu'à sa validation, qui lui attribue un numéro définitif et la verrouille.</p></div>
</div>
<?php \App\Core\View::partial('doc_form', [
    'kind' => 'invoice', 'doc' => $doc, 'items' => $items, 'customer' => $customer, 'taxes' => $taxes, 'units' => $units,
    'action' => url($doc ? 'invoices/' . $doc['id'] : 'invoices'), 'cancelUrl' => url($doc ? 'invoices/' . $doc['id'] : 'invoices'),
]); ?>
