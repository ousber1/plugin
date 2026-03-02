<?php
/**
 * BERRADI PRINT - Gestion des Utilisateurs Admin
 */
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['ajouter_admin'])) {
        $mdp = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO admins (nom, prenom, email, telephone, mot_de_passe, role) VALUES (?,?,?,?,?,?)")->execute([
            clean($_POST['nom']), clean($_POST['prenom']), clean($_POST['email']),
            clean($_POST['telephone']), $mdp, clean($_POST['role'])
        ]);
        setFlash('success', 'Utilisateur ajouté.');
        redirect('index.php?page=admins');
    }
    if (isset($_POST['modifier_admin'])) {
        $admin_id_mod = (int)$_POST['admin_id'];
        $db->prepare("UPDATE admins SET nom=?, prenom=?, email=?, telephone=?, role=?, actif=? WHERE id=?")->execute([
            clean($_POST['nom']), clean($_POST['prenom']), clean($_POST['email']),
            clean($_POST['telephone']), clean($_POST['role']),
            isset($_POST['actif']) ? 1 : 0, $admin_id_mod
        ]);
        if (!empty($_POST['nouveau_mdp'])) {
            $db->prepare("UPDATE admins SET mot_de_passe = ? WHERE id = ?")->execute([password_hash($_POST['nouveau_mdp'], PASSWORD_DEFAULT), $admin_id_mod]);
        }
        setFlash('success', 'Utilisateur mis à jour.');
        redirect('index.php?page=admins');
    }
}

$admins_list = $db->query("SELECT * FROM admins ORDER BY role, nom")->fetchAll();
$roles = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'operateur' => 'Opérateur'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-person-gear me-2"></i>Utilisateurs (<?= count($admins_list) ?>)</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdmin"><i class="bi bi-plus-circle me-1"></i>Nouvel utilisateur</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light"><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Rôle</th><th>Dernière connexion</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($admins_list as $a): ?>
                <tr class="<?= !$a['actif'] ? 'opacity-50' : '' ?>">
                    <td class="fw-bold"><?= $a['prenom'] ?> <?= $a['nom'] ?></td>
                    <td><?= $a['email'] ?></td>
                    <td><?= $a['telephone'] ?></td>
                    <td><span class="badge bg-<?= $a['role'] === 'super_admin' ? 'danger' : ($a['role'] === 'admin' ? 'primary' : 'secondary') ?>"><?= $roles[$a['role']] ?? $a['role'] ?></span></td>
                    <td><small><?= $a['derniere_connexion'] ? dateFormatFr($a['derniere_connexion'], 'complet') : 'Jamais' ?></small></td>
                    <td><?= $a['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
                    <td><button class="btn btn-sm btn-outline-primary" onclick="modAdmin(<?= htmlspecialchars(json_encode($a)) ?>)"><i class="bi bi-pencil"></i></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajouter -->
<div class="modal fade" id="modalAdmin" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-header"><h5 class="modal-title">Nouvel utilisateur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Prénom *</label><input type="text" name="prenom" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Téléphone</label><input type="tel" name="telephone" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Rôle</label>
                            <select name="role" class="form-select">
                                <?php foreach ($roles as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Mot de passe *</label><input type="password" name="mot_de_passe" class="form-control" required minlength="6"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="ajouter_admin" class="btn btn-primary">Ajouter</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal fade" id="modalModAdmin" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="admin_id" id="ma_id">
                <div class="modal-header"><h5 class="modal-title">Modifier l'utilisateur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Prénom</label><input type="text" name="prenom" id="ma_prenom" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Nom</label><input type="text" name="nom" id="ma_nom" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" id="ma_email" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Téléphone</label><input type="tel" name="telephone" id="ma_tel" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Rôle</label>
                            <select name="role" id="ma_role" class="form-select">
                                <?php foreach ($roles as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label><input type="password" name="nouveau_mdp" class="form-control" minlength="6"></div>
                        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="actif" id="ma_actif"><label class="form-check-label" for="ma_actif">Actif</label></div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="modifier_admin" class="btn btn-primary">Sauvegarder</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function modAdmin(a) {
    document.getElementById('ma_id').value = a.id;
    document.getElementById('ma_prenom').value = a.prenom;
    document.getElementById('ma_nom').value = a.nom;
    document.getElementById('ma_email').value = a.email;
    document.getElementById('ma_tel').value = a.telephone || '';
    document.getElementById('ma_role').value = a.role;
    document.getElementById('ma_actif').checked = a.actif == 1;
    new bootstrap.Modal(document.getElementById('modalModAdmin')).show();
}
</script>
