<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Services\Auth;

/**
 * "Mes créations": the customer's saved custom designs and private photos.
 */
final class DesignController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::customer();
        $db = Database::instance();
        $designs = $db->fetchAll(
            'SELECT uuid, width_cm, height_cm, panels, unit_price, quality_status, status, preview_path, config, created_at
             FROM custom_designs WHERE customer_id = ? ORDER BY created_at DESC LIMIT 100',
            [(int) $customer['id']]
        );
        foreach ($designs as &$d) {
            $d['config'] = json_decode((string) $d['config'], true) ?: [];
        }
        unset($d);
        $photos = $db->fetchAll(
            "SELECT uuid, original_name, width_px, height_px, size_bytes, status, retention_until, created_at
             FROM uploaded_images WHERE customer_id = ? AND status <> 'purged' ORDER BY created_at DESC LIMIT 200",
            [(int) $customer['id']]
        );
        return $this->view('customer/account/designs', compact('customer', 'designs', 'photos'));
    }
}
