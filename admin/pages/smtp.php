<?php
/**
 * BERRADI PRINT - Configuration SMTP & Templates Email
 */
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['save_smtp'])) {
        $smtp_keys = ['smtp_active', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name'];
        foreach ($smtp_keys as $k) {
            setParametre($k, $_POST[$k] ?? '');
        }
        setFlash('success', 'Configuration SMTP sauvegardée.');
        redirect('index.php?page=smtp&tab=smtp');
    }

    if (isset($_POST['test_smtp'])) {
        $to = clean($_POST['test_email']);
        $subject = 'Test SMTP - ' . APP_NAME;
        $message = 'Ceci est un email de test envoyé depuis ' . APP_NAME . '. Si vous recevez ce message, votre configuration SMTP fonctionne correctement.';
        $headers = 'From: ' . getParametre('smtp_from_name', APP_NAME) . ' <' . getParametre('smtp_from_email', APP_EMAIL) . '>' . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        if (@mail($to, $subject, $message, $headers)) {
            setFlash('success', 'Email de test envoyé à ' . htmlspecialchars($to));
        } else {
            setFlash('danger', 'Échec de l\'envoi. Vérifiez votre configuration SMTP.');
        }
        redirect('index.php?page=smtp&tab=smtp');
    }

    if (isset($_POST['save_templates'])) {
        $templates = ['email_confirmation_commande', 'email_statut_commande', 'email_nouveau_devis', 'email_signature'];
        foreach ($templates as $t) {
            setParametre($t, $_POST[$t] ?? '');
        }
        setFlash('success', 'Templates email sauvegardés.');
        redirect('index.php?page=smtp&tab=templates');
    }

    if (isset($_POST['save_notifications'])) {
        $notif_keys = ['email_notif_commande', 'email_notif_devis', 'email_notif_contact', 'email_notif_destination'];
        foreach ($notif_keys as $k) {
            setParametre($k, $_POST[$k] ?? '');
        }
        setFlash('success', 'Notifications email sauvegardées.');
        redirect('index.php?page=smtp&tab=notifications');
    }
}

$tab = $_GET['tab'] ?? 'smtp';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-envelope-at me-2"></i>SMTP & Emails</h4>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $tab === 'smtp' ? 'active' : '' ?>" href="?page=smtp&tab=smtp">Configuration SMTP</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'templates' ? 'active' : '' ?>" href="?page=smtp&tab=templates">Templates Email</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'notifications' ? 'active' : '' ?>" href="?page=smtp&tab=notifications">Notifications</a></li>
</ul>

<?php if ($tab === 'smtp'): ?>
<!-- SMTP CONFIG -->
<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Serveur SMTP</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="smtp_active" id="smtp_active" value="1" <?= getParametre('smtp_active') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="smtp_active">Activer l'envoi SMTP</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Serveur SMTP</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars(getParametre('smtp_host')) ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Port</label>
                            <select name="smtp_port" class="form-select">
                                <option value="587" <?= getParametre('smtp_port', '587') == '587' ? 'selected' : '' ?>>587 (TLS)</option>
                                <option value="465" <?= getParametre('smtp_port') == '465' ? 'selected' : '' ?>>465 (SSL)</option>
                                <option value="25" <?= getParametre('smtp_port') == '25' ? 'selected' : '' ?>>25 (Non sécurisé)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom d'utilisateur</label>
                            <input type="text" name="smtp_username" class="form-control" value="<?= htmlspecialchars(getParametre('smtp_username')) ?>" placeholder="user@gmail.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mot de passe</label>
                            <div class="input-group">
                                <input type="password" name="smtp_password" id="smtp_pass" class="form-control" value="<?= htmlspecialchars(getParametre('smtp_password')) ?>">
                                <button type="button" class="btn btn-outline-secondary" onclick="let f=document.getElementById('smtp_pass');f.type=f.type==='password'?'text':'password'"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Chiffrement</label>
                            <select name="smtp_encryption" class="form-select">
                                <option value="tls" <?= getParametre('smtp_encryption', 'tls') == 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= getParametre('smtp_encryption') == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="" <?= getParametre('smtp_encryption') == '' ? 'selected' : '' ?>>Aucun</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email expéditeur</label>
                            <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars(getParametre('smtp_from_email', APP_EMAIL)) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nom expéditeur</label>
                            <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars(getParametre('smtp_from_name', APP_NAME)) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Tester la configuration</div>
                <div class="card-body">
                    <div class="input-group">
                        <input type="email" name="test_email" class="form-control" placeholder="votre@email.com">
                        <button type="submit" name="test_smtp" class="btn btn-outline-primary">
                            <i class="bi bi-send me-1"></i>Envoyer un test
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" name="save_smtp" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle me-2"></i>Sauvegarder la configuration
            </button>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Configuration courante</h6>
                    <div class="small">
                        <p class="fw-bold mb-1">Gmail</p>
                        <p class="text-muted">Serveur: smtp.gmail.com<br>Port: 587 (TLS)<br>Utilisez un mot de passe d'application</p>
                        <p class="fw-bold mb-1">Outlook / Office 365</p>
                        <p class="text-muted">Serveur: smtp.office365.com<br>Port: 587 (TLS)</p>
                        <p class="fw-bold mb-1">OVH</p>
                        <p class="text-muted mb-0">Serveur: ssl0.ovh.net<br>Port: 465 (SSL)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php elseif ($tab === 'templates'): ?>
<!-- EMAIL TEMPLATES -->
<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Confirmation de commande</div>
                <div class="card-body">
                    <textarea name="email_confirmation_commande" class="form-control" rows="8" placeholder="Bonjour {client_nom},

Merci pour votre commande #{numero_commande} !

Récapitulatif :
{details_commande}

Total : {total}

Nous vous contacterons bientôt pour confirmer votre commande.

Cordialement,
{nom_entreprise}"><?= htmlspecialchars(getParametre('email_confirmation_commande')) ?></textarea>
                    <small class="text-muted">
                        Variables disponibles: {client_nom}, {numero_commande}, {details_commande}, {total}, {nom_entreprise}, {telephone}, {email}
                    </small>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Changement de statut de commande</div>
                <div class="card-body">
                    <textarea name="email_statut_commande" class="form-control" rows="6" placeholder="Bonjour {client_nom},

Votre commande #{numero_commande} est maintenant : {statut}

{message_statut}

Cordialement,
{nom_entreprise}"><?= htmlspecialchars(getParametre('email_statut_commande')) ?></textarea>
                    <small class="text-muted">
                        Variables: {client_nom}, {numero_commande}, {statut}, {message_statut}, {nom_entreprise}
                    </small>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Envoi de devis</div>
                <div class="card-body">
                    <textarea name="email_nouveau_devis" class="form-control" rows="6" placeholder="Bonjour {client_nom},

Veuillez trouver ci-joint votre devis #{numero_devis}.

Total TTC : {total}
Validité : {date_validite}

N'hésitez pas à nous contacter pour toute question.

Cordialement,
{nom_entreprise}"><?= htmlspecialchars(getParametre('email_nouveau_devis')) ?></textarea>
                    <small class="text-muted">
                        Variables: {client_nom}, {numero_devis}, {total}, {date_validite}, {nom_entreprise}
                    </small>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Signature email</div>
                <div class="card-body">
                    <textarea name="email_signature" class="form-control" rows="4" placeholder="--
{nom_entreprise}
{telephone}
{email}
{adresse}"><?= htmlspecialchars(getParametre('email_signature')) ?></textarea>
                    <small class="text-muted">Ajoutée à la fin de chaque email</small>
                </div>
            </div>

            <button type="submit" name="save_templates" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle me-2"></i>Sauvegarder les templates
            </button>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Variables</h6>
                    <p class="small text-muted">Utilisez les variables entre accolades {variable} dans vos templates. Elles seront remplacées automatiquement lors de l'envoi.</p>
                    <table class="table table-sm small">
                        <tr><td class="font-monospace">{client_nom}</td><td>Nom du client</td></tr>
                        <tr><td class="font-monospace">{numero_commande}</td><td>N° commande</td></tr>
                        <tr><td class="font-monospace">{numero_devis}</td><td>N° devis</td></tr>
                        <tr><td class="font-monospace">{total}</td><td>Montant total</td></tr>
                        <tr><td class="font-monospace">{statut}</td><td>Statut actuel</td></tr>
                        <tr><td class="font-monospace">{nom_entreprise}</td><td>Nom du site</td></tr>
                        <tr><td class="font-monospace">{telephone}</td><td>Téléphone</td></tr>
                        <tr><td class="font-monospace">{email}</td><td>Email</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>

<?php elseif ($tab === 'notifications'): ?>
<!-- NOTIFICATION SETTINGS -->
<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Notifications par email</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Email de destination des notifications</label>
                        <input type="email" name="email_notif_destination" class="form-control" value="<?= htmlspecialchars(getParametre('email_notif_destination', APP_EMAIL)) ?>" placeholder="admin@votresite.com">
                        <small class="text-muted">Adresse qui recevra les notifications admin</small>
                    </div>
                    <hr>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="email_notif_commande" id="notif_cmd" value="1" <?= getParametre('email_notif_commande') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notif_cmd">
                            <strong>Nouvelle commande</strong><br>
                            <small class="text-muted">Recevoir un email à chaque nouvelle commande</small>
                        </label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="email_notif_devis" id="notif_devis" value="1" <?= getParametre('email_notif_devis') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notif_devis">
                            <strong>Demande de devis</strong><br>
                            <small class="text-muted">Recevoir un email à chaque demande de devis</small>
                        </label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="email_notif_contact" id="notif_contact" value="1" <?= getParametre('email_notif_contact') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notif_contact">
                            <strong>Message de contact</strong><br>
                            <small class="text-muted">Recevoir un email à chaque message via le formulaire de contact</small>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" name="save_notifications" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle me-2"></i>Sauvegarder
            </button>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Aide</h6>
                    <p class="small text-muted mb-0">Activez les notifications que vous souhaitez recevoir par email. La configuration SMTP doit être active pour envoyer les emails.</p>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>
