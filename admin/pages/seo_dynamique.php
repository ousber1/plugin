<?php
/**
 * BERRADI PRINT - SEO Dynamique
 * Gestion des meta title/description par produit, catégorie et page
 */
$db = getDB();

// Auto-add meta columns
try { $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE produits ADD COLUMN IF NOT EXISTS meta_description TEXT DEFAULT NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE categories ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE categories ADD COLUMN IF NOT EXISTS meta_description TEXT DEFAULT NULL"); } catch (Exception $e) {}

$tab = $_GET['tab'] ?? 'produits';

// Save SEO data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_seo_dynamic'])) {
    verifyCsrf();
    $type = clean($_POST['type'] ?? '');
    $item_id = (int)$_POST['item_id'];
    $meta_title = clean($_POST['meta_title'] ?? '');
    $meta_description = clean($_POST['meta_description'] ?? '');

    if ($item_id && in_array($type, ['produit', 'categorie', 'page'])) {
        $table = $type === 'produit' ? 'produits' : ($type === 'categorie' ? 'categories' : 'pages');
        $db->prepare("UPDATE $table SET meta_title = ?, meta_description = ? WHERE id = ?")->execute([$meta_title ?: null, $meta_description ?: null, $item_id]);
        setFlash('success', 'SEO mis à jour avec succès.');
    }
    redirect('index.php?page=seo_dynamique&tab=' . $tab);
}

// Bulk auto-generate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_generate'])) {
    verifyCsrf();
    $type = clean($_POST['gen_type'] ?? '');
    $count = 0;

    if ($type === 'produits') {
        $items = $db->query("SELECT id, nom, description_courte FROM produits WHERE (meta_title IS NULL OR meta_title = '') AND actif = 1")->fetchAll();
        foreach ($items as $item) {
            $title = $item['nom'] . ' - ' . APP_NAME;
            $desc = $item['description_courte'] ?: $item['nom'] . ' - Services d\'impression professionnels | ' . APP_NAME;
            $db->prepare("UPDATE produits SET meta_title = ?, meta_description = ? WHERE id = ?")->execute([substr($title, 0, 70), substr($desc, 0, 160), $item['id']]);
            $count++;
        }
    } elseif ($type === 'categories') {
        $items = $db->query("SELECT id, nom, description FROM categories WHERE (meta_title IS NULL OR meta_title = '') AND actif = 1")->fetchAll();
        foreach ($items as $item) {
            $title = $item['nom'] . ' - ' . APP_NAME;
            $desc = $item['description'] ?: $item['nom'] . ' - Catalogue de services d\'impression | ' . APP_NAME;
            $db->prepare("UPDATE categories SET meta_title = ?, meta_description = ? WHERE id = ?")->execute([substr($title, 0, 70), substr($desc, 0, 160), $item['id']]);
            $count++;
        }
    } elseif ($type === 'pages') {
        $items = $db->query("SELECT id, titre FROM pages WHERE (meta_title IS NULL OR meta_title = '') AND actif = 1")->fetchAll();
        foreach ($items as $item) {
            $title = $item['titre'] . ' - ' . APP_NAME;
            $db->prepare("UPDATE pages SET meta_title = ? WHERE id = ?")->execute([substr($title, 0, 70), $item['id']]);
            $count++;
        }
    }

    setFlash('success', "$count éléments mis à jour automatiquement.");
    redirect('index.php?page=seo_dynamique&tab=' . $type);
}

// Load data
$produits = $db->query("SELECT id, nom, slug, meta_title, meta_description, actif FROM produits ORDER BY nom")->fetchAll();
$categories_list = $db->query("SELECT id, nom, slug, meta_title, meta_description, actif FROM categories ORDER BY ordre")->fetchAll();
$pages_list = $db->query("SELECT id, titre, slug, meta_title, meta_description, actif FROM pages ORDER BY titre")->fetchAll();

// Stats
$total_produits = count($produits);
$seo_produits = count(array_filter($produits, fn($p) => !empty($p['meta_title'])));
$total_cats = count($categories_list);
$seo_cats = count(array_filter($categories_list, fn($c) => !empty($c['meta_title'])));
$total_pages = count($pages_list);
$seo_pages = count(array_filter($pages_list, fn($p) => !empty($p['meta_title'])));
$total_all = $total_produits + $total_cats + $total_pages;
$seo_all = $seo_produits + $seo_cats + $seo_pages;
$pct = $total_all > 0 ? round($seo_all / $total_all * 100) : 0;

$edit_id = (int)($_GET['edit'] ?? 0);
$edit_type = clean($_GET['type'] ?? '');
?>

<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-graph-up me-2"></i>SEO Dynamique</h4>
            <p class="text-muted mb-0">Optimisez le SEO de chaque produit, catégorie et page</p>
        </div>
        <a href="index.php?page=seo" class="btn btn-outline-primary btn-sm"><i class="bi bi-gear me-1"></i>SEO Global</a>
    </div>

    <!-- Score SEO -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block mb-2">
                        <svg width="80" height="80"><circle cx="40" cy="40" r="35" fill="none" stroke="#e9ecef" stroke-width="6"/><circle cx="40" cy="40" r="35" fill="none" stroke="<?= $pct >= 80 ? '#198754' : ($pct >= 50 ? '#ffc107' : '#dc3545') ?>" stroke-width="6" stroke-dasharray="<?= round($pct * 2.2) ?> 220" stroke-dashoffset="0" transform="rotate(-90 40 40)" stroke-linecap="round"/></svg>
                        <div class="position-absolute top-50 start-50 translate-middle fw-bold fs-5"><?= $pct ?>%</div>
                    </div>
                    <div class="fw-bold small">Score SEO</div>
                    <div class="text-muted small"><?= $seo_all ?>/<?= $total_all ?> optimisés</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-box text-primary fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Produits</div>
                        <div class="fw-bold"><?= $seo_produits ?>/<?= $total_produits ?></div>
                        <div class="progress mt-1" style="height:4px;width:80px;"><div class="progress-bar bg-primary" style="width:<?= $total_produits ? round($seo_produits/$total_produits*100) : 0 ?>%"></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-grid text-success fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Catégories</div>
                        <div class="fw-bold"><?= $seo_cats ?>/<?= $total_cats ?></div>
                        <div class="progress mt-1" style="height:4px;width:80px;"><div class="progress-bar bg-success" style="width:<?= $total_cats ? round($seo_cats/$total_cats*100) : 0 ?>%"></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="bi bi-file-earmark text-info fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Pages</div>
                        <div class="fw-bold"><?= $seo_pages ?>/<?= $total_pages ?></div>
                        <div class="progress mt-1" style="height:4px;width:80px;"><div class="progress-bar bg-info" style="width:<?= $total_pages ? round($seo_pages/$total_pages*100) : 0 ?>%"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link <?= $tab === 'produits' ? 'active' : '' ?>" href="?page=seo_dynamique&tab=produits"><i class="bi bi-box me-1"></i>Produits (<?= $total_produits ?>)</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'categories' ? 'active' : '' ?>" href="?page=seo_dynamique&tab=categories"><i class="bi bi-grid me-1"></i>Catégories (<?= $total_cats ?>)</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'pages' ? 'active' : '' ?>" href="?page=seo_dynamique&tab=pages"><i class="bi bi-file-earmark me-1"></i>Pages (<?= $total_pages ?>)</a></li>
    </ul>

    <?php
    $items = [];
    $type_key = '';
    if ($tab === 'produits') { $items = $produits; $type_key = 'produit'; }
    elseif ($tab === 'categories') { $items = $categories_list; $type_key = 'categorie'; }
    elseif ($tab === 'pages') { $items = $pages_list; $type_key = 'page'; }
    ?>

    <!-- Auto-generate button -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="small text-muted">
            <?php
            $missing = count(array_filter($items, fn($i) => empty($i['meta_title'])));
            if ($missing > 0): ?>
            <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= $missing ?> sans meta title</span>
            <?php else: ?>
            <span class="text-success"><i class="bi bi-check-circle me-1"></i>Tous optimisés</span>
            <?php endif; ?>
        </div>
        <?php if ($missing > 0): ?>
        <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="gen_type" value="<?= $tab ?>">
            <button type="submit" name="auto_generate" class="btn btn-outline-primary btn-sm" onclick="return confirm('Générer automatiquement le SEO pour les <?= $missing ?> éléments manquants ?')">
                <i class="bi bi-magic me-1"></i>Auto-générer (<?= $missing ?>)
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width:30px"></th>
                            <th><?= $tab === 'pages' ? 'Titre' : 'Nom' ?></th>
                            <th>Meta Title</th>
                            <th>Meta Description</th>
                            <th style="width:100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item):
                            $name = $item['nom'] ?? $item['titre'] ?? '';
                            $has_seo = !empty($item['meta_title']);
                            $is_editing = ($edit_id == $item['id'] && $edit_type === $type_key);
                        ?>
                        <?php if ($is_editing): ?>
                        <tr class="table-primary">
                            <td><i class="bi bi-pencil text-primary"></i></td>
                            <td colspan="4">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="type" value="<?= $type_key ?>">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <div class="fw-bold mb-2"><?= htmlspecialchars($name) ?></div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold mb-1">Meta Title <span class="text-muted">(max 70 car.)</span></label>
                                        <input type="text" name="meta_title" class="form-control form-control-sm" maxlength="70" value="<?= htmlspecialchars($item['meta_title'] ?? '') ?>" placeholder="<?= htmlspecialchars($name) ?> - <?= APP_NAME ?>" id="editTitle">
                                        <div class="form-text"><span id="titleCount">0</span>/70</div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold mb-1">Meta Description <span class="text-muted">(max 160 car.)</span></label>
                                        <textarea name="meta_description" class="form-control form-control-sm" rows="2" maxlength="160" placeholder="Description pour les moteurs de recherche..." id="editDesc"><?= htmlspecialchars($item['meta_description'] ?? '') ?></textarea>
                                        <div class="form-text"><span id="descCount">0</span>/160</div>
                                    </div>
                                    <!-- Google Preview -->
                                    <div class="border rounded p-2 bg-white mb-2 small">
                                        <div class="text-muted mb-1"><i class="bi bi-google me-1"></i>Aperçu Google</div>
                                        <div style="color:#1a0dab;font-size:14px;" id="previewTitle"><?= htmlspecialchars($item['meta_title'] ?: $name . ' - ' . APP_NAME) ?></div>
                                        <div style="color:#006621;font-size:12px;"><?= APP_URL ?>/index.php?page=...</div>
                                        <div style="color:#545454;font-size:12px;" id="previewDesc"><?= htmlspecialchars($item['meta_description'] ?: '') ?></div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="save_seo_dynamic" class="btn btn-primary btn-sm"><i class="bi bi-check me-1"></i>Enregistrer</button>
                                        <a href="?page=seo_dynamique&tab=<?= $tab ?>" class="btn btn-outline-secondary btn-sm">Annuler</a>
                                    </div>
                                </form>
                                <script>
                                const et=document.getElementById('editTitle'),ed=document.getElementById('editDesc');
                                const tc=document.getElementById('titleCount'),dc=document.getElementById('descCount');
                                const pt=document.getElementById('previewTitle'),pd=document.getElementById('previewDesc');
                                function upd(){tc.textContent=et.value.length;dc.textContent=ed.value.length;pt.textContent=et.value||'<?= addslashes($name) ?> - <?= addslashes(APP_NAME) ?>';pd.textContent=ed.value;}
                                et.addEventListener('input',upd);ed.addEventListener('input',upd);upd();
                                </script>
                            </td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td>
                                <?php if ($has_seo): ?>
                                <i class="bi bi-check-circle-fill text-success" title="SEO configuré"></i>
                                <?php else: ?>
                                <i class="bi bi-exclamation-circle text-warning" title="SEO manquant"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($name) ?></div>
                                <?php if (!$item['actif']): ?><small class="badge bg-secondary">Inactif</small><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['meta_title']): ?>
                                <span class="small"><?= htmlspecialchars(mb_substr($item['meta_title'], 0, 50)) ?><?= mb_strlen($item['meta_title']) > 50 ? '...' : '' ?></span>
                                <?php else: ?>
                                <span class="text-muted small">Non défini</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['meta_description']): ?>
                                <span class="small"><?= htmlspecialchars(mb_substr($item['meta_description'], 0, 60)) ?><?= mb_strlen($item['meta_description']) > 60 ? '...' : '' ?></span>
                                <?php else: ?>
                                <span class="text-muted small">Non défini</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=seo_dynamique&tab=<?= $tab ?>&edit=<?= $item['id'] ?>&type=<?= $type_key ?>" class="btn btn-outline-primary btn-sm" title="Modifier SEO">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun élément trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
