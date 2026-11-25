<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\DocumentNumber;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Uploader;
use App\Services\SettingsSchema;

/**
 * Paramètres de l'application (permission settings.manage).
 * Onglets génériques pilotés par SettingsSchema + onglets spécifiques :
 * TVA (table taxes), numérotation (document_sequences), notifications.
 */
final class SettingsController extends Controller
{
    public function index(): void
    {
        $this->redirect('settings/business');
    }

    public function show(string $tab): void
    {
        $tabs = SettingsSchema::tabs();
        if (!isset($tabs[$tab])) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }
        $data = [
            'title'  => 'Paramètres',
            'tabs'   => $tabs,
            'tab'    => $tab,
            'fields' => SettingsSchema::fields($tab),
            'values' => Settings::all(),
        ];
        if ($tab === 'currency') {
            $data['taxes'] = $this->db->fetchAll(
                'SELECT t.*, (SELECT COUNT(*) FROM products p WHERE p.tax_id = t.id AND p.deleted_at IS NULL) AS used FROM taxes t ORDER BY t.rate DESC'
            );
            $data['defaultTaxId'] = (int) Settings::get('default_tax_id', 1);
        }
        if ($tab === 'numbering') {
            $data['sequences'] = $this->db->fetchAll('SELECT * FROM document_sequences ORDER BY id');
        }
        if ($tab === 'templates') {
            $data['templates'] = $this->db->fetchAll("SELECT * FROM document_templates WHERE doc_type IN ('quotation','invoice','deposit','balance','credit_note','receipt','sales_order') ORDER BY id");
        }
        if ($tab === 'notifications') {
            $data['notificationSettings'] = $this->db->fetchAll('SELECT * FROM notification_settings ORDER BY id');
            $data['roles'] = $this->db->fetchAll('SELECT id, name FROM roles ORDER BY id');
        }
        $this->view('settings/index', $data);
    }

    /** Enregistrement d'un onglet générique. */
    public function save(string $tab): void
    {
        match ($tab) {
            'numbering'     => $this->saveSequences(),
            'notifications' => $this->saveNotifications(),
            'templates'     => $this->saveTemplates(),
            default         => $this->saveGeneric($tab),
        };
    }

    private function saveGeneric(string $tab): void
    {
        $fields = SettingsSchema::fields($tab);
        if ($fields === []) {
            $this->redirect('settings');
        }
        $errors = [];
        $values = [];
        foreach ($fields as $key => [$label, $type, $opt]) {
            if ($type === 'image') {
                continue;
            }
            $raw = $type === 'bool' ? (Request::post($key) ? '1' : '0') : (string) Request::post($key, '');
            $raw = is_string($raw) ? trim($raw) : '';

            if ($type === 'password') {
                if ($raw !== '') {
                    $values[$key] = [Crypto::encrypt($raw), 'string', $opt['group']];
                }
                continue;
            }
            if (!empty($opt['required']) && $raw === '') {
                $errors[] = "« {$label} » est obligatoire.";
                continue;
            }
            if ($raw !== '') {
                $error = $this->validateValue($label, $type, $raw, $opt);
                if ($error !== null) {
                    $errors[] = $error;
                    continue;
                }
            }
            $dbType = match ($type) {
                'int'     => 'int',
                'decimal' => 'decimal',
                'bool'    => 'bool',
                'textarea'=> 'text',
                default   => 'string',
            };
            if ($type === 'decimal') {
                $raw = (string) Request::decimal($key);
            }
            if ($type === 'phone') {
                $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;
            }
            if ($type === 'select' && $raw === '' && isset($opt['options'][''])) {
                $raw = '';
            }
            $values[$key] = [$raw, $dbType, $opt['group']];
        }

        // Images (logo, cachet, signature)
        $images = [];
        foreach ($fields as $key => [$label, $type, $opt]) {
            if ($type !== 'image' || !Uploader::has($key)) {
                continue;
            }
            try {
                $images[$key] = [Uploader::publicImage($key, 'branding', 2048, $key === 'business_logo' ? 1200 : 800), $opt['group']];
            } catch (\Throwable $e) {
                $errors[] = "« {$label} » : " . $e->getMessage();
            }
        }

        if ($errors) {
            foreach ($images as [$path]) {
                Uploader::deletePublic($path);
            }
            Session::flash('danger', implode(' ', $errors));
            Session::flashInput($_POST);
            $this->redirect('settings/' . $tab);
        }

        $before = Settings::all();
        $this->db->transaction(function () use ($values, $images): void {
            foreach ($values as $key => [$value, $type, $group]) {
                Settings::set($key, $type === 'bool' ? $value === '1' : $value, $group, $type);
            }
            foreach ($images as $key => [$path, $group]) {
                Settings::set($key, $path, $group, 'string');
            }
        });
        foreach ($images as $key => $_) {
            Uploader::deletePublic((string) ($before[$key] ?? ''));
        }

        $changed = [];
        foreach ($values + $images as $key => [$value]) {
            if ((string) ($before[$key] ?? '') !== (string) ($value === true ? '1' : $value)) {
                $changed[$key] = $value;
            }
        }
        $old = array_intersect_key($before, $changed);
        AuditLog::log('update', 'settings', 'settings', null, 'Paramètres modifiés : ' . SettingsSchema::tabs()[$tab][0], $old, $changed);
        Session::flash('success', 'Paramètres enregistrés.');
        $this->redirect('settings/' . $tab);
    }

    private function validateValue(string $label, string $type, string $raw, array $opt): ?string
    {
        switch ($type) {
            case 'email':
                return filter_var($raw, FILTER_VALIDATE_EMAIL) ? null : "« {$label} » : adresse e-mail invalide.";
            case 'url':
                return filter_var($raw, FILTER_VALIDATE_URL) && preg_match('#^https?://#', $raw) ? null : "« {$label} » : URL invalide (https://…).";
            case 'phone':
                return preg_match('/^\+?[0-9 ().\-]{6,20}$/', $raw) ? null : "« {$label} » : numéro invalide.";
            case 'color':
                return preg_match('/^#[0-9a-fA-F]{6}$/', $raw) ? null : "« {$label} » : couleur invalide.";
            case 'select':
                return array_key_exists($raw, $opt['options']) ? null : "« {$label} » : valeur invalide.";
            case 'int':
            case 'decimal':
                $n = str_replace([' ', ','], ['', '.'], $raw);
                if (!is_numeric($n) || ($type === 'int' && (string) (int) $n !== ltrim($n, '+'))) {
                    return "« {$label} » doit être un nombre" . ($type === 'int' ? ' entier.' : '.');
                }
                if (isset($opt['min']) && (float) $n < $opt['min']) {
                    return "« {$label} » doit être supérieur ou égal à {$opt['min']}.";
                }
                if (isset($opt['max']) && (float) $n > $opt['max']) {
                    return "« {$label} » doit être inférieur ou égal à {$opt['max']}.";
                }
                return null;
        }
        if (isset($opt['max']) && mb_strlen($raw) > $opt['max']) {
            return "« {$label} » : {$opt['max']} caractères maximum.";
        }
        if (isset($opt['pattern']) && !preg_match($opt['pattern'], $raw)) {
            return $opt['pattern_msg'] ?? "« {$label} » : format invalide.";
        }
        return null;
    }

    /** Suppression d'une image (logo, cachet, signature). */
    public function removeImage(): void
    {
        $key = Request::string('key');
        $allowed = ['business_logo' => 'business', 'stamp_image' => 'pdf', 'signature_image' => 'pdf'];
        if (!isset($allowed[$key])) {
            $this->redirect('settings');
        }
        Uploader::deletePublic((string) Settings::get($key, ''));
        Settings::set($key, '', $allowed[$key], 'string');
        AuditLog::log('delete', 'settings', 'settings', null, 'Image supprimée : ' . $key);
        Session::flash('success', 'Image supprimée.');
        $this->redirect('settings/' . $allowed[$key]);
    }

    /* ------------------------------------------------------------------
       TVA
       ------------------------------------------------------------------ */

    public function saveTax(): void
    {
        $id = Request::int('id');
        $name = Request::string('name');
        $rate = Request::decimal('rate', -1);
        if ($name === '' || mb_strlen($name) > 60 || $rate < 0 || $rate > 100) {
            Session::flash('danger', 'Taux de TVA invalide : nom obligatoire et taux entre 0 et 100 %.');
            $this->redirect('settings/currency');
        }
        $data = ['name' => $name, 'rate' => round($rate, 3), 'is_active' => Request::post('is_active') ? 1 : 0];
        if ($id > 0) {
            $before = $this->db->fetch('SELECT * FROM taxes WHERE id = ?', [$id]);
            if (!$before) {
                $this->redirect('settings/currency');
            }
            if ((int) Settings::get('default_tax_id') === $id) {
                $data['is_active'] = 1; // le taux par défaut reste actif
            }
            $this->db->update('taxes', $data, 'id = :id', ['id' => $id]);
            [$old, $new] = AuditLog::diff($before, $data);
            AuditLog::log('update', 'settings', 'tax', $id, 'Taux de TVA modifié : ' . $name, $old, $new);
        } else {
            $id = $this->db->insert('taxes', $data);
            AuditLog::log('create', 'settings', 'tax', $id, 'Taux de TVA ajouté : ' . $name, null, $data);
        }
        if (Request::post('is_default')) {
            $this->db->transaction(function () use ($id): void {
                $this->db->query('UPDATE taxes SET is_default = (id = ?)', [$id]);
                $this->db->query('UPDATE taxes SET is_active = 1 WHERE id = ?', [$id]);
                Settings::set('default_tax_id', $id, 'tax', 'int');
            });
        }
        Session::flash('success', 'Taux de TVA enregistré.');
        $this->redirect('settings/currency');
    }

    /* ------------------------------------------------------------------
       Numérotation
       ------------------------------------------------------------------ */

    private function saveSequences(): void
    {
        $rows = $_POST['seq'] ?? [];
        if (!is_array($rows)) {
            $this->redirect('settings/numbering');
        }
        $errors = [];
        $updates = [];
        $prefixes = [];
        foreach ($this->db->fetchAll('SELECT * FROM document_sequences') as $seq) {
            $in = $rows[$seq['doc_type']] ?? null;
            if (!is_array($in)) {
                continue;
            }
            $prefix = strtoupper(trim((string) ($in['prefix'] ?? '')));
            $format = trim((string) ($in['format'] ?? ''));
            $padding = (int) ($in['padding'] ?? 0);
            $next = (int) ($in['next_number'] ?? 0);
            $reset = (string) ($in['reset_policy'] ?? '');
            $label = $seq['label'];

            if (!preg_match('/^[A-Z0-9]{1,10}$/', $prefix)) {
                $errors[] = "{$label} : préfixe invalide (1 à 10 lettres ou chiffres).";
            } elseif (isset($prefixes[$prefix])) {
                $errors[] = "{$label} : le préfixe « {$prefix} » est déjà utilisé par « {$prefixes[$prefix]} ».";
            }
            $prefixes[$prefix] = $label;
            if (!DocumentNumber::isValidFormat($format)) {
                $errors[] = "{$label} : format invalide (doit contenir {SEQ}).";
            }
            if ($padding < 1 || $padding > 10) {
                $errors[] = "{$label} : nombre de chiffres entre 1 et 10.";
            }
            if ($next < 1) {
                $errors[] = "{$label} : prochain numéro invalide.";
            }
            if (!in_array($reset, ['never', 'yearly', 'monthly'], true)) {
                $errors[] = "{$label} : remise à zéro invalide.";
            }
            $changed = $prefix !== $seq['prefix'] || $format !== $seq['format'] || $padding !== (int) $seq['padding']
                || $next !== (int) $seq['next_number'] || $reset !== $seq['reset_policy'];
            if ($changed) {
                // Le numéro ne peut pas redescendre sous un numéro déjà attribué dans la période en cours
                if ($next < (int) $seq['next_number'] && $prefix === $seq['prefix'] && $format === $seq['format'] && $reset === $seq['reset_policy']) {
                    $errors[] = "{$label} : le prochain numéro ne peut pas être inférieur à {$seq['next_number']} (risque de doublon).";
                }
                $updates[$seq['id']] = [
                    'data'   => ['prefix' => $prefix, 'format' => $format, 'padding' => $padding, 'next_number' => $next, 'reset_policy' => $reset]
                        + ($reset !== $seq['reset_policy'] ? ['current_period' => null] : []),
                    'before' => $seq,
                ];
            }
        }
        if ($errors) {
            Session::flash('danger', implode(' ', array_slice($errors, 0, 6)));
            $this->redirect('settings/numbering');
        }
        $this->db->transaction(function () use ($updates): void {
            foreach ($updates as $id => $u) {
                $this->db->update('document_sequences', $u['data'], 'id = :id', ['id' => $id]);
                [$old, $new] = AuditLog::diff($u['before'], $u['data']);
                AuditLog::log('update', 'settings', 'document_sequence', (int) $id, 'Numérotation modifiée : ' . $u['before']['label'], $old, $new);
            }
        });
        Session::flash('success', $updates ? count($updates) . ' séquence(s) de numérotation mise(s) à jour.' : 'Aucune modification.');
        $this->redirect('settings/numbering');
    }

    /* ------------------------------------------------------------------
       Notifications
       ------------------------------------------------------------------ */

    /** Modèles de documents : titre, textes d'en-tête / pied, conditions, couleur, éléments affichés. */
    private function saveTemplates(): void
    {
        $id = Request::int('template_id');
        $tpl = $this->db->fetch('SELECT * FROM document_templates WHERE id = ?', [$id]);
        if (!$tpl) {
            $this->fail('Modèle introuvable.', 'settings/templates');
        }
        $title = mb_substr(Request::string('title'), 0, 100);
        $color = Request::string('accent_color');
        if ($title === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $this->fail('Titre obligatoire et couleur au format #RRGGBB.', 'settings/templates', ['edit' => $id]);
        }
        $clean = static fn(string $k, int $max) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max) ?: null;
        $new = [
            'title' => $title, 'header_text' => $clean('header_text', 2000), 'footer_text' => $clean('footer_text', 500), 'terms' => $clean('terms', 3000),
            'accent_color' => strtolower($color), 'show_logo' => Request::post('show_logo') ? 1 : 0, 'show_qr' => Request::post('show_qr') ? 1 : 0,
            'show_signature' => Request::post('show_signature') ? 1 : 0, 'show_stamp' => Request::post('show_stamp') ? 1 : 0,
        ];
        $this->db->update('document_templates', $new, 'id = :id', ['id' => $id]);
        AuditLog::log('update', 'settings', 'document_template', $id, 'Modèle « ' . $tpl['name'] . ' » modifié',
            array_intersect_key($tpl, $new), $new);
        Session::flash('success', 'Modèle « ' . $tpl['name'] . ' » enregistré.');
        $this->redirect('settings/templates', ['edit' => $id]);
    }

    private function saveNotifications(): void
    {
        $rows = $_POST['notif'] ?? [];
        $roleIds = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM roles'), 'id'));
        $this->db->transaction(function () use ($rows, $roleIds): void {
            foreach ($this->db->fetchAll('SELECT * FROM notification_settings') as $ns) {
                $in = is_array($rows) ? ($rows[$ns['type']] ?? []) : [];
                $role = (int) ($in['role'] ?? 0);
                $days = ($in['days'] ?? '') === '' ? null : max(0, min(90, (int) $in['days']));
                $this->db->update('notification_settings', [
                    'is_enabled'     => !empty($in['enabled']) ? 1 : 0,
                    'notify_role_id' => in_array($role, $roleIds, true) ? $role : null,
                    'days_before'    => $ns['days_before'] === null ? null : ($days ?? (int) $ns['days_before']),
                ], 'id = :id', ['id' => $ns['id']]);
            }
        });
        AuditLog::log('update', 'settings', 'notification_settings', null, 'Paramètres des notifications modifiés');
        Session::flash('success', 'Paramètres des notifications enregistrés.');
        $this->redirect('settings/notifications');
    }

    /* ------------------------------------------------------------------
       Test d'envoi d'e-mail
       ------------------------------------------------------------------ */

    public function testMail(): void
    {
        $user = Auth::user();
        $mailer = new Mailer();
        $ok = $mailer->send(
            (string) $user['email'],
            'Test d\'envoi — ' . Settings::get('business_name', 'HO CREATIVE'),
            Mailer::template('Test de configuration e-mail', '<p>Bonjour ' . e($user['full_name']) . ',</p><p>Si vous lisez ce message, l\'envoi d\'e-mails fonctionne correctement.</p>')
        );
        if ($ok) {
            Session::flash('success', 'E-mail de test envoyé à ' . $user['email'] . '.');
        } else {
            Session::flash('danger', 'Échec de l\'envoi : ' . $mailer->lastError());
        }
        $this->redirect('settings/mail');
    }
}
