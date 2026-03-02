<?php
/**
 * BERRADI PRINT - Nouveau Devis
 */
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_devis'])) {
    verifyCsrf();

    $numero = genererNumeroDevis();
    $nom = clean($_POST['prenom'] . ' ' . $_POST['nom']);
    $tel = clean($_POST['telephone']);
    $email = clean($_POST['email'] ?? '');

    $lignes = [];
    $sous_total = 0;
    foreach ($_POST['lignes'] ?? [] as $l) {
        if (!empty($l['designation'])) {
            $total_l = floatval($l['prix_unitaire']) * intval($l['quantite']);
            $lignes[] = ['designation' => $l['designation'], 'quantite' => $l['quantite'], 'prix_unitaire' => $l['prix_unitaire'], 'total' => $total_l];
            $sous_total += $total_l;
        }
    }

    $tva = $sous_total * TAX_RATE;
    $total = $sous_total + $tva;

    $db->prepare("INSERT INTO devis (numero_devis, client_nom, client_telephone, client_email, lignes, sous_total, tva_montant, total, statut, date_validite, notes, admin_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
        $numero, $nom, $tel, $email, json_encode($lignes), $sous_total, $tva, $total,
        'brouillon', date('Y-m-d', strtotime('+30 days')), clean($_POST['notes'] ?? ''), $admin['id']
    ]);

    setFlash('success', "Devis #$numero créé.");
    redirect('index.php?page=devis');
}
?>

<div class="mb-4">
    <a href="index.php?page=devis" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    <h4 class="fw-bold"><i class="bi bi-plus-circle me-2"></i>Nouveau Devis</h4>
</div>

<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Client</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label small">Prénom *</label><input type="text" name="prenom" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">Téléphone *</label><input type="tel" name="telephone" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small">Email</label><input type="email" name="email" class="form-control"></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Lignes du devis</span>
                    <button type="button" class="btn btn-sm btn-primary" onclick="ajouterLigneDevis()"><i class="bi bi-plus me-1"></i>Ajouter</button>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead class="bg-light"><tr><th>Désignation</th><th style="width:80px">Qté</th><th style="width:120px">Prix unit.</th><th style="width:120px">Total</th><th style="width:40px"></th></tr></thead>
                        <tbody id="lignesDevis">
                            <tr class="ligne-devis">
                                <td><input type="text" name="lignes[0][designation]" class="form-control form-control-sm" required></td>
                                <td><input type="number" name="lignes[0][quantite]" class="form-control form-control-sm dqte" value="1" min="1" onchange="calcDevis()"></td>
                                <td><input type="number" name="lignes[0][prix_unitaire]" class="form-control form-control-sm dprix" step="0.01" value="0" onchange="calcDevis()"></td>
                                <td class="dtotal fw-bold text-end align-middle">0,00 DH</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();calcDevis()"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Sous-total HT</span><span id="dSousTotal" class="fw-bold">0,00 DH</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>TVA (20%)</span><span id="dTVA">0,00 DH</span></div>
                    <hr>
                    <div class="d-flex justify-content-between"><span class="fw-bold fs-5">Total TTC</span><span id="dTotal" class="fw-bold fs-5 text-primary">0,00 DH</span></div>
                </div>
            </div>
            <div class="mb-3"><label class="form-label small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="3"></textarea></div>
            <button type="submit" name="creer_devis" class="btn btn-primary btn-lg w-100"><i class="bi bi-check-circle me-2"></i>Créer le devis</button>
        </div>
    </div>
</form>

<script>
let dIdx = 1;
function ajouterLigneDevis() {
    const tr = document.createElement('tr');
    tr.className = 'ligne-devis';
    tr.innerHTML = `<td><input type="text" name="lignes[${dIdx}][designation]" class="form-control form-control-sm" required></td>
    <td><input type="number" name="lignes[${dIdx}][quantite]" class="form-control form-control-sm dqte" value="1" min="1" onchange="calcDevis()"></td>
    <td><input type="number" name="lignes[${dIdx}][prix_unitaire]" class="form-control form-control-sm dprix" step="0.01" value="0" onchange="calcDevis()"></td>
    <td class="dtotal fw-bold text-end align-middle">0,00 DH</td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();calcDevis()"><i class="bi bi-trash"></i></button></td>`;
    document.getElementById('lignesDevis').appendChild(tr);
    dIdx++;
}
function calcDevis() {
    let st = 0;
    document.querySelectorAll('.ligne-devis').forEach(tr => {
        const q = parseFloat(tr.querySelector('.dqte').value) || 0;
        const p = parseFloat(tr.querySelector('.dprix').value) || 0;
        const t = q * p; st += t;
        tr.querySelector('.dtotal').textContent = t.toFixed(2).replace('.', ',') + ' DH';
    });
    const tva = st * 0.2;
    document.getElementById('dSousTotal').textContent = st.toFixed(2).replace('.', ',') + ' DH';
    document.getElementById('dTVA').textContent = tva.toFixed(2).replace('.', ',') + ' DH';
    document.getElementById('dTotal').textContent = (st + tva).toFixed(2).replace('.', ',') + ' DH';
}
</script>
