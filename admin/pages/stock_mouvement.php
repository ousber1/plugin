<?php
/**
 * BERRADI PRINT - Nouveau Mouvement de Stock
 */
$db = getDB();

// Auto-create stock columns
try {
    $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS stock_quantite INT DEFAULT 0");
    $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS stock_min INT DEFAULT 0");
    $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS stock_actif TINYINT(1) DEFAULT 0");
} catch (Exception $e) {}

$produit_preselect = (int)($_GET['produit_id'] ?? 0);
$type_preselect = $_GET['type'] ?? 'entree';

// Get products with stock tracking enabled
$produits = $db->query("SELECT id, nom, stock_quantite, unite FROM produits WHERE stock_actif = 1 AND actif = 1 ORDER BY nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer'])) {
    verifyCsrf();
    $pid = (int)$_POST['produit_id'];
    $type = clean($_POST['type_mouvement']);
    $qte = abs((int)$_POST['quantite']);
    $reference = clean($_POST['reference'] ?? '');
    $motif = clean($_POST['motif'] ?? '');
    $fournisseur = clean($_POST['fournisseur'] ?? '');
    $cout = floatval($_POST['cout_unitaire'] ?? 0);
    $notes = clean($_POST['notes'] ?? '');

    if ($pid && $qte > 0 && in_array($type, ['entree', 'sortie', 'ajustement', 'retour'])) {
        $stmt = $db->prepare("SELECT stock_quantite, nom FROM produits WHERE id = ? AND stock_actif = 1");
        $stmt->execute([$pid]);
        $p = $stmt->fetch();

        if ($p) {
            $old_qty = (int)$p['stock_quantite'];
            if (in_array($type, ['entree', 'retour'])) {
                $new_qty = $old_qty + $qte;
            } elseif ($type === 'sortie') {
                $new_qty = max(0, $old_qty - $qte);
            } else {
                $new_qty = $qte; // ajustement = set exact value
                $qte = abs($new_qty - $old_qty);
            }

            $db->prepare("UPDATE produits SET stock_quantite = ? WHERE id = ?")->execute([$new_qty, $pid]);
            $db->prepare("INSERT INTO stock_mouvements (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, reference, motif, fournisseur, cout_unitaire, notes, admin_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$pid, $type, $qte, $old_qty, $new_qty, $reference ?: null, $motif ?: null, $fournisseur ?: null, $cout ?: null, $notes ?: null, $admin['id']]);

            setFlash('success', "Mouvement enregistré: <strong>" . htmlspecialchars($p['nom']) . "</strong> — $old_qty → $new_qty");
            redirect('index.php?page=stock');
        } else {
            setFlash('danger', 'Produit introuvable ou suivi de stock désactivé.');
        }
    } else {
        setFlash('danger', 'Veuillez remplir tous les champs obligatoires.');
    }
}
?>

<div class="mb-4">
    <a href="index.php?page=stock" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour au stock</a>
    <h4 class="fw-bold mt-2"><i class="bi bi-plus-circle me-2"></i>Nouveau mouvement de stock</h4>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="POST">
            <?= csrfField() ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Type de mouvement *</label>
                            <select name="type_mouvement" id="typeMouvement" class="form-select" required onchange="updateUI()">
                                <option value="entree" <?= $type_preselect === 'entree' ? 'selected' : '' ?>>📥 Entrée de stock</option>
                                <option value="sortie" <?= $type_preselect === 'sortie' ? 'selected' : '' ?>>📤 Sortie de stock</option>
                                <option value="ajustement" <?= $type_preselect === 'ajustement' ? 'selected' : '' ?>>🔄 Ajustement (valeur exacte)</option>
                                <option value="retour" <?= $type_preselect === 'retour' ? 'selected' : '' ?>>↩️ Retour</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Produit *</label>
                            <select name="produit_id" id="produitSelect" class="form-select" required onchange="updateStockInfo()">
                                <option value="">-- Sélectionner un produit --</option>
                                <?php foreach ($produits as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock_quantite'] ?>" data-unite="<?= htmlspecialchars($p['unite']) ?>" <?= $produit_preselect == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nom']) ?> (Stock: <?= $p['stock_quantite'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($produits)): ?>
                            <small class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Aucun produit avec suivi de stock actif. <a href="index.php?page=stock">Activez le suivi</a> d'abord.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" id="qteLabel">Quantité *</label>
                            <input type="number" name="quantite" id="quantite" class="form-control form-control-lg" required min="1" placeholder="0">
                            <div id="stockActuel" class="form-text"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Référence</label>
                            <input type="text" name="reference" class="form-control" placeholder="N° BL, facture, etc.">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Coût unitaire (DH)</label>
                            <input type="number" name="cout_unitaire" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-6" id="fournisseurField">
                            <label class="form-label">Fournisseur</label>
                            <input type="text" name="fournisseur" class="form-control" placeholder="Nom du fournisseur">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Motif</label>
                            <input type="text" name="motif" class="form-control" placeholder="Raison du mouvement">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Notes supplémentaires..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button type="submit" name="enregistrer" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Enregistrer le mouvement
                    </button>
                    <a href="index.php?page=stock" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" id="previewCard">
            <div class="card-header bg-white fw-bold"><i class="bi bi-info-circle me-2"></i>Aperçu</div>
            <div class="card-body" id="previewBody">
                <p class="text-muted small">Sélectionnez un produit et renseignez la quantité pour voir l'aperçu du mouvement.</p>
            </div>
        </div>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-lightbulb me-2"></i>Guide</div>
            <div class="card-body small">
                <div class="mb-2"><span class="badge bg-success me-1">Entrée</span> Réception de marchandise, achat</div>
                <div class="mb-2"><span class="badge bg-danger me-1">Sortie</span> Utilisation, perte, casse</div>
                <div class="mb-2"><span class="badge bg-info me-1">Ajustement</span> Correction d'inventaire (valeur exacte)</div>
                <div class="mb-2"><span class="badge bg-warning text-dark me-1">Retour</span> Retour de marchandise</div>
            </div>
        </div>
    </div>
</div>

<script>
function updateUI() {
    const type = document.getElementById('typeMouvement').value;
    const fField = document.getElementById('fournisseurField');
    const qLabel = document.getElementById('qteLabel');
    fField.style.display = (type === 'entree' || type === 'retour') ? '' : 'none';
    if (type === 'ajustement') {
        qLabel.textContent = 'Nouvelle quantité exacte *';
    } else {
        qLabel.textContent = 'Quantité *';
    }
    updatePreview();
}

function updateStockInfo() {
    const sel = document.getElementById('produitSelect');
    const opt = sel.options[sel.selectedIndex];
    const info = document.getElementById('stockActuel');
    if (opt && opt.value) {
        info.innerHTML = '<i class="bi bi-box me-1"></i>Stock actuel: <strong>' + opt.dataset.stock + '</strong> ' + (opt.dataset.unite || '');
    } else {
        info.innerHTML = '';
    }
    updatePreview();
}

function updatePreview() {
    const sel = document.getElementById('produitSelect');
    const opt = sel.options[sel.selectedIndex];
    const type = document.getElementById('typeMouvement').value;
    const qte = parseInt(document.getElementById('quantite').value) || 0;
    const body = document.getElementById('previewBody');

    if (!opt || !opt.value || qte <= 0) {
        body.innerHTML = '<p class="text-muted small">Sélectionnez un produit et renseignez la quantité.</p>';
        return;
    }

    const stock = parseInt(opt.dataset.stock) || 0;
    let newStock = stock;
    if (type === 'entree' || type === 'retour') newStock = stock + qte;
    else if (type === 'sortie') newStock = Math.max(0, stock - qte);
    else newStock = qte;

    const color = newStock > stock ? 'success' : (newStock < stock ? 'danger' : 'info');
    body.innerHTML = `
        <div class="fw-bold mb-2">${opt.text.split(' (Stock')[0]}</div>
        <div class="d-flex align-items-center justify-content-center gap-3 py-3">
            <div class="text-center">
                <div class="text-muted small">Avant</div>
                <div class="fs-4 fw-bold">${stock}</div>
            </div>
            <i class="bi bi-arrow-right fs-4 text-${color}"></i>
            <div class="text-center">
                <div class="text-muted small">Après</div>
                <div class="fs-4 fw-bold text-${color}">${newStock}</div>
            </div>
        </div>
        ${newStock === 0 ? '<div class="alert alert-danger py-1 px-2 small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Attention: rupture de stock!</div>' : ''}
    `;
}

document.getElementById('quantite').addEventListener('input', updatePreview);
updateUI();
updateStockInfo();
</script>
