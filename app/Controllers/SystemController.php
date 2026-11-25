<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\DocumentNumber;

/**
 * Pages système : accueil provisoire (Phase 1) et état de l'installation.
 */
final class SystemController extends Controller
{
    /**
     * Accueil après connexion. En Phase 1, affiche l'état réel du système ;
     * le tableau de bord statistique le remplacera en Phase 2.
     */
    public function home(): void
    {
        $this->view('system/status', $this->collectStatus() + ['title' => 'Accueil']);
    }

    public function status(): void
    {
        $this->view('system/status', $this->collectStatus() + ['title' => 'État du système']);
    }

    private function collectStatus(): array
    {
        $dbName = (string) Config::get('db.name');
        $tables = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?',
            [$dbName]
        );
        $counts = [];
        foreach ([
            'users' => 'Utilisateurs', 'roles' => 'Rôles', 'permissions' => 'Permissions',
            'units' => 'Unités', 'taxes' => 'Taux de TVA', 'categories' => 'Catégories',
            'machines' => 'Machines', 'cost_parameters' => 'Paramètres de coûts',
            'document_sequences' => 'Séquences de numérotation', 'customers' => 'Clients',
        ] as $table => $label) {
            $counts[$label] = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM `$table`");
        }
        $sequences = [];
        foreach ($this->db->fetchAll('SELECT doc_type, label FROM document_sequences ORDER BY id') as $s) {
            $sequences[] = ['label' => $s['label'], 'next' => DocumentNumber::preview($s['doc_type'])];
        }
        return [
            'tables'     => $tables,
            'counts'     => $counts,
            'sequences'  => $sequences,
            'dbVersion'  => (string) $this->db->fetchColumn('SELECT VERSION()'),
            'schema'     => (string) $this->db->fetchColumn('SELECT MAX(version) FROM schema_migrations'),
            'phpVersion' => PHP_VERSION,
            'installedAt'=> (string) Config::get('app.installed_at', ''),
            'lastLogs'   => $this->db->fetchAll(
                'SELECT l.created_at, l.action, l.description, u.full_name
                   FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
                  ORDER BY l.id DESC LIMIT 8'
            ),
        ];
    }
}
