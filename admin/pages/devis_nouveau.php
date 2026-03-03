<?php
/**
 * BERRADI PRINT - Nouveau Devis (avec sélection produits)
 */
$db = getDB();

// Load products for selection
$produits_list = $db->query("SELECT id, nom, prix_base, unite FROM produits WHERE actif = 1 ORDER BY nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_devis'])) {
    verifyCsrf();

    $numero = genererNumeroDevis();
    $prenom = clean($_POST['prenom'] ?? '');
    $nom_client = clean($_POST['nom'] ?? '');
    $full_name = trim($prenom . ' ' . $nom_client);
    $tel = clean($_POST['telephone'] ?? '');
    $email = clean($_POST['email'] ?? '');

    $lignes = [];
    $sous_total = 0;
    foreach ($_POST['lignes'] ?? [] as $l) {
        if (!empty($l['designation'])) {
            $qte = intval($l['quantite'] ?: 1);
            $pu = floatval($l['prix_unitaire'] ?: 0);
            $total_l = $pu * $qte;
            $lignes[] = [
                'designation' => clean($l['designation']),
                'quantite' => $qte,
                'prix_unitaire' => $pu,
                'total' => $total_l
            ];
            $sous_total += $total_l;
        }
    }

    if (empty($lignes)) {
        setFlash('danger', 'Ajoutez au moins une ligne au devis.');
        redirect('index.php?page=devis_nouveau');
    }

    $tva_rate = isset($_POST['avec_tva']) ? TAX_RATE : 0;
    $tva = $sous_total * $tva_rate;
    $remise = floatval($_POST['remise'] ?? 0);
    $total = $sous_total + $tva - $remise;

    $validite = clean($_POST['date_validite'] ?? date('Y-m-d', strtotime('+30 days')));

    $db->prepare("INSERT INTO devis (numero_devis, client_nom, client_telephone, client_email, lignes, sous_total, tva_montant, total, statut, date_validite, notes, admin_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
        $numero, $full_name, $tel, $email, json_encode($lignes),
        $sous_total, $tva, $total, 'brouillon', $validite,
        clean($_POST['notes'] ?? ''), $admin['id']
    ]);

    setFlash('success', "Devis <strong>#$numero</strong> créé avec succès.");
    redirect('index.php?page=devis');
}
?>

<div class="mb-4">
    <a href="index.php?page=devis" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour aux devis</a>
    <h4 class="fw-bold"><i class="bi bi-plus-circle me-2"></i>Nouveau Devis</h4>
</div>

<form method="POST" id="formDevis">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Client Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-person me-2"></i>Client</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Prénom *</label>
                            <input type="text" name="prenom" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Nom *</label>
                            <input type="text" name="nom" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Téléphone *</label>
                            <input type="tel" name="telephone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lignes du devis -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-list-check me-2"></i>Lignes du devis</span>
                    <div class="d-flex gap-2">
                        <select id="selectProduit" class="form-select form-select-sm" style="width:auto;">
                            <option value="">+ Produit du catalogue</option>
                            <?php foreach ($produits_list as $p): ?>
                            <option value="<?= $p['id'] ?>" data-nom="<?= htmlspecialchars($p['nom']) ?>" data-prix="<?= $p['prix_base'] ?>"><?= htmlspecialchars($p['nom']) ?> - <?= formatPrix($p['prix_base']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="ajouterLigneDevis()">
                            <i class="bi bi-plus me-1"></i>Ligne vide
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Désignation</th>
                                <th style="width:80px">Qté</th>
                                <th style="width:130px">Prix unit. (DH)</th>
                                <th style="width:120px" class="text-end">Total</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="lignesDevis">
                            <tr class="ligne-devis">
                                <td><input type="text" name="lignes[0][designation]" class="form-control form-control-sm" required placeholder="Désignation..."></td>
                                <td><input type="number" name="lignes[0][quantite]" class="form-control form-control-sm dqte" value="1" min="1" onchange="calcDevis()" onkeyup="calcDevis()"></td>
                                <td><input type="number" name="lignes[0][prix_unitaire]" class="form-control form-control-sm dprix" step="0.01" value="0" onchange="calcDevis()" onkeyup="calcDevis()"></td>
                                <td class="dtotal fw-bold text-end align-middle">0,00 DH</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();calcDevis()"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold">Récapitulatif</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Sous-total HT</span>
                        <span id="dSousTotal" class="fw-bold">0,00 DH</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="avec_tva" id="avec_tva" value="1" checked onchange="calcDevis()">
                            <label class="form-check-label" for="avec_tva">TVA (20%)</label>
                        </div>
                        <span id="dTVA">0,00 DH</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 align-items-center">
                        <label class="form-label mb-0 small">Remise (DH)</label>
                        <input type="number" name="remise" id="dRemise" class="form-control form-control-sm" style="width:100px;" value="0" step="0.01" onchange="calcDevis()" onkeyup="calcDevis()">
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold fs-5">Total TTC</span>
                        <span id="dTotal" class="fw-bold fs-5 text-primary">0,00 DH</span>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small">Date de validité</label>
                        <input type="date" name="date_validite" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small">Notes internes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Notes visibles uniquement par l'admin..."></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" name="creer_devis" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-check-circle me-2"></i>Créer le devis
            </button>
        </div>
    </div>
</form>

<script>
let dIdx = 1;

// Add product from catalog select
document.getElementById('selectProduit').addEventListener('change', function() {
    if (!this.value) return;
    const opt = this.options[this.selectedIndex];
    ajouterLigneDevis(opt.dataset.nom, 1, opt.dataset.prix);
    this.value = '';
});

function ajouterLigneDevis(designation, qte, prix) {
    designation = designation || '';
    qte = qte || 1;
    prix = prix || 0;
    const tr = document.createElement('tr');
    tr.className = 'ligne-devis';
    tr.innerHTML = `<td><input type="text" name="lignes[${dIdx}][designation]" class="form-control form-control-sm" required value="${designation}" placeholder="Désignation..."></td>
    <td><input type="number" name="lignes[${dIdx}][quantite]" class="form-control form-control-sm dqte" value="${qte}" min="1" onchange="calcDevis()" onkeyup="calcDevis()"></td>
    <td><input type="number" name="lignes[${dIdx}][prix_unitaire]" class="form-control form-control-sm dprix" step="0.01" value="${prix}" onchange="calcDevis()" onkeyup="calcDevis()"></td>
    <td class="dtotal fw-bold text-end align-middle">0,00 DH</td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();calcDevis()"><i class="bi bi-trash"></i></button></td>`;
    document.getElementById('lignesDevis').appendChild(tr);
    dIdx++;
    calcDevis();
}

function calcDevis() {
    let st = 0;
    document.querySelectorAll('.ligne-devis').forEach(tr => {
        const qEl = tr.querySelector('.dqte');
        const pEl = tr.querySelector('.dprix');
        if (!qEl || !pEl) return;
        const q = parseFloat(qEl.value) || 0;
        const p = parseFloat(pEl.value) || 0;
        const t = q * p;
        st += t;
        const totalEl = tr.querySelector('.dtotal');
        if (totalEl) totalEl.textContent = formatDH(t);
    });
    const avecTva = document.getElementById('avec_tva').checked;
    const tva = avecTva ? st * 0.2 : 0;
    const remise = parseFloat(document.getElementById('dRemise').value) || 0;
    const total = st + tva - remise;
    document.getElementById('dSousTotal').textContent = formatDH(st);
    document.getElementById('dTVA').textContent = formatDH(tva);
    document.getElementById('dTotal').textContent = formatDH(total);
}

function formatDH(val) {
    return val.toFixed(2).replace('.', ',') + ' DH';
}
</script>
