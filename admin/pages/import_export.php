<?php
/**
 * BERRADI PRINT - Import / Export de Données
 */

$db = getDB();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Export CSV
    if ($action === 'export') {
        $table = $_POST['table'] ?? '';
        $allowed_tables = ['produits', 'categories', 'clients', 'commandes', 'villes_livraison', 'depenses'];

        if (in_array($table, $allowed_tables)) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $table . '_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            $stmt = $db->query("SELECT * FROM `{$table}`");
            $first = true;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($first) {
                    fputcsv($output, array_keys($row), ';');
                    $first = false;
                }
                fputcsv($output, $row, ';');
            }
            fclose($output);
            exit;
        }
    }

    // Import CSV
    if ($action === 'import') {
        $table = $_POST['import_table'] ?? '';
        $mode = $_POST['import_mode'] ?? 'append';
        $allowed_tables = ['produits', 'categories', 'clients', 'villes_livraison'];

        if (!in_array($table, $allowed_tables)) {
            setFlash('danger', 'Table non autorisée pour l\'import.');
            redirect('index.php?page=import_export');
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            setFlash('danger', 'Erreur lors de l\'upload du fichier.');
            redirect('index.php?page=import_export');
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            setFlash('danger', 'Seuls les fichiers CSV sont acceptés.');
            redirect('index.php?page=import_export');
        }

        try {
            $handle = fopen($file['tmp_name'], 'r');
            // Détecter et ignorer BOM
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
                rewind($handle);
            }

            // Lire l'en-tête
            $header = fgetcsv($handle, 0, ';');
            if (!$header) {
                throw new Exception('Fichier CSV vide ou format invalide.');
            }

            // Nettoyer les noms de colonnes
            $header = array_map('trim', $header);

            // Obtenir les colonnes de la table
            $table_columns = $db->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_COLUMN);

            // Filtrer les colonnes valides
            $valid_columns = array_intersect($header, $table_columns);
            if (empty($valid_columns)) {
                throw new Exception('Aucune colonne correspondante trouvée dans le fichier CSV.');
            }

            // Préparer l'import
            if ($mode === 'replace') {
                // Ne pas supprimer les commandes ou données critiques
                if (in_array($table, ['clients', 'categories'])) {
                    $db->exec("DELETE FROM `{$table}`");
                }
            }

            $imported = 0;
            $errors = 0;
            $column_indexes = [];
            foreach ($valid_columns as $col) {
                $column_indexes[$col] = array_search($col, $header);
            }

            $placeholders = implode(', ', array_fill(0, count($valid_columns), '?'));
            $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $valid_columns));

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                if (empty(array_filter($row))) continue;

                try {
                    $values = [];
                    foreach ($column_indexes as $col => $idx) {
                        $values[] = isset($row[$idx]) ? trim($row[$idx]) : null;
                    }

                    // Skip l'ID si c'est auto-increment et mode append
                    $cols_to_insert = array_values($valid_columns);
                    $vals_to_insert = $values;

                    if ($mode === 'append' && in_array('id', $cols_to_insert)) {
                        $id_idx = array_search('id', $cols_to_insert);
                        unset($cols_to_insert[$id_idx]);
                        unset($vals_to_insert[$id_idx]);
                        $cols_to_insert = array_values($cols_to_insert);
                        $vals_to_insert = array_values($vals_to_insert);
                    }

                    if (empty($cols_to_insert)) continue;

                    $col_sql = implode(', ', array_map(function($c) { return "`{$c}`"; }, $cols_to_insert));
                    $ph = implode(', ', array_fill(0, count($cols_to_insert), '?'));

                    $stmt = $db->prepare("INSERT INTO `{$table}` ({$col_sql}) VALUES ({$ph})");
                    $stmt->execute($vals_to_insert);
                    $imported++;
                } catch (Exception $e) {
                    $errors++;
                }
            }
            fclose($handle);

            $msg = "{$imported} enregistrement(s) importé(s) avec succès.";
            if ($errors > 0) {
                $msg .= " {$errors} erreur(s) ignorée(s).";
            }
            setFlash('success', $msg);
            redirect('index.php?page=import_export');
        } catch (Exception $e) {
            setFlash('danger', 'Erreur d\'import: ' . $e->getMessage());
            redirect('index.php?page=import_export');
        }
    }

    // Import SQL
    if ($action === 'import_sql') {
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            setFlash('danger', 'Erreur lors de l\'upload du fichier SQL.');
            redirect('index.php?page=import_export');
        }

        $file = $_FILES['sql_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            setFlash('danger', 'Seuls les fichiers .sql sont acceptés.');
            redirect('index.php?page=import_export');
        }

        try {
            $sql = file_get_contents($file['tmp_name']);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $executed = 0;
            $errors = 0;

            foreach ($statements as $statement) {
                if (empty($statement)) continue;
                // Bloquer les commandes dangereuses
                $lower = strtolower($statement);
                if (strpos($lower, 'drop database') !== false || strpos($lower, 'drop table') !== false) {
                    $errors++;
                    continue;
                }
                try {
                    $db->exec($statement);
                    $executed++;
                } catch (Exception $e) {
                    $errors++;
                }
            }

            $msg = "{$executed} requête(s) exécutée(s) avec succès.";
            if ($errors > 0) {
                $msg .= " {$errors} requête(s) ignorée(s).";
            }
            setFlash('success', $msg);
            redirect('index.php?page=import_export');
        } catch (Exception $e) {
            setFlash('danger', 'Erreur SQL: ' . $e->getMessage());
            redirect('index.php?page=import_export');
        }
    }

    // Réinstaller les données par défaut
    if ($action === 'reset_data') {
        try {
            $sql_file = __DIR__ . '/../../database/schema.sql';
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                // Retirer CREATE DATABASE et USE
                $sql = preg_replace('/CREATE DATABASE.*?;\s*/i', '', $sql);
                $sql = preg_replace('/USE\s+\w+\s*;\s*/i', '', $sql);

                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $statement) {
                    if (empty($statement)) continue;
                    try {
                        $db->exec($statement);
                    } catch (Exception $e) {
                        // Ignorer les erreurs de tables existantes
                    }
                }

                setFlash('success', 'Données par défaut réinstallées avec succès.');
            } else {
                setFlash('danger', 'Fichier schema.sql introuvable.');
            }
            redirect('index.php?page=import_export');
        } catch (Exception $e) {
            setFlash('danger', 'Erreur: ' . $e->getMessage());
            redirect('index.php?page=import_export');
        }
    }
}

// Obtenir les statistiques des tables
$tables_info = [];
$tables = ['admins', 'clients', 'categories', 'produits', 'commandes', 'commande_lignes', 'devis', 'depenses', 'villes_livraison', 'parametres', 'notifications'];
foreach ($tables as $t) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        $tables_info[$t] = $count;
    } catch (Exception $e) {
        $tables_info[$t] = 0;
    }
}
?>

<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-arrow-left-right me-2"></i>Import / Export de Données</h4>
            <p class="text-muted mb-0">Importez et exportez vos données en CSV ou SQL</p>
        </div>
    </div>

    <!-- Statistiques des tables -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-database text-primary me-2"></i>État de la Base de Données</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php
                $icons = [
                    'admins' => 'bi-person-gear', 'clients' => 'bi-people', 'categories' => 'bi-tags',
                    'produits' => 'bi-box', 'commandes' => 'bi-cart-check', 'commande_lignes' => 'bi-list-check',
                    'devis' => 'bi-file-earmark-text', 'depenses' => 'bi-cash-stack',
                    'villes_livraison' => 'bi-geo-alt', 'parametres' => 'bi-gear', 'notifications' => 'bi-bell',
                ];
                foreach ($tables_info as $table => $count):
                ?>
                <div class="col-md-3 col-sm-4 col-6 mb-3">
                    <div class="border rounded p-2 text-center">
                        <i class="bi <?= $icons[$table] ?? 'bi-table' ?> text-primary fs-5"></i>
                        <div class="fw-bold"><?= $count ?></div>
                        <small class="text-muted"><?= $table ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Export -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-download text-success me-2"></i>Exporter des Données (CSV)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Exportez vos données au format CSV pour sauvegarde ou analyse.</p>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="export">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Table à exporter</label>
                            <select class="form-select" name="table" required>
                                <option value="">Sélectionnez...</option>
                                <option value="produits">Produits (<?= $tables_info['produits'] ?>)</option>
                                <option value="categories">Catégories (<?= $tables_info['categories'] ?>)</option>
                                <option value="clients">Clients (<?= $tables_info['clients'] ?>)</option>
                                <option value="commandes">Commandes (<?= $tables_info['commandes'] ?>)</option>
                                <option value="villes_livraison">Villes de livraison (<?= $tables_info['villes_livraison'] ?>)</option>
                                <option value="depenses">Dépenses (<?= $tables_info['depenses'] ?>)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-download me-1"></i>Exporter en CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Import CSV -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-upload text-primary me-2"></i>Importer des Données (CSV)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Importez des données depuis un fichier CSV (séparateur: point-virgule).</p>
                    <form method="post" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="import">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Table cible</label>
                            <select class="form-select" name="import_table" required>
                                <option value="">Sélectionnez...</option>
                                <option value="produits">Produits</option>
                                <option value="categories">Catégories</option>
                                <option value="clients">Clients</option>
                                <option value="villes_livraison">Villes de livraison</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mode d'import</label>
                            <select class="form-select" name="import_mode">
                                <option value="append">Ajouter aux données existantes</option>
                                <option value="replace">Remplacer les données existantes</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Fichier CSV</label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                            <div class="form-text">Format: CSV avec séparateur point-virgule (;), encodage UTF-8</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload me-1"></i>Importer le CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Import SQL -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-filetype-sql text-warning me-2"></i>Importer un Fichier SQL</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Exécutez un fichier SQL directement sur la base de données.</p>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Les commandes DROP DATABASE et DROP TABLE sont bloquées par sécurité.
                    </div>
                    <form method="post" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="import_sql">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Fichier SQL</label>
                            <input type="file" class="form-control" name="sql_file" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="bi bi-play-fill me-1"></i>Exécuter le SQL
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Réinitialisation -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-arrow-counterclockwise text-danger me-2"></i>Données par Défaut</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Réinstallez les données par défaut (catégories, produits, villes, paramètres).</p>
                    <div class="alert alert-danger small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Attention:</strong> Cette action peut écraser des données existantes si les tables existent déjà. Les données dupliquées seront ignorées.
                    </div>
                    <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir réinstaller les données par défaut?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reset_data">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Réinstaller les Données par Défaut
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
