<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Translator;

/**
 * Homepage. Phase 1 renders the brand hero and real categories from the database;
 * the full premium homepage (collections, best sellers, reviews, gallery...) is built in Phase 2.
 */
final class HomeController extends Controller
{
    public function index(): Response
    {
        $db = Database::instance();
        $categories = $db->fetchAll(
            "SELECT c.id, c.slug, c.name, c.image, t.value AS name_ar
             FROM categories c
             LEFT JOIN translations t ON t.entity_type = 'category' AND t.entity_id = c.id AND t.locale = 'ar' AND t.field = 'name'
             WHERE c.is_active = 1 ORDER BY c.sort_order, c.name LIMIT 13"
        );
        foreach ($categories as &$c) {
            $c['label'] = Translator::locale() === 'ar' && !empty($c['name_ar']) ? $c['name_ar'] : $c['name'];
        }
        unset($c);
        return $this->view('storefront/home', ['categories' => $categories]);
    }
}
