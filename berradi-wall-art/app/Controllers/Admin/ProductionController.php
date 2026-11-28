<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\Operations\InventoryService;
use App\Services\Operations\ProductionException;
use App\Services\Operations\ProductionService;
use App\Services\Sales\OrderStatus;
use App\Services\Sales\WorkflowException;

/**
 * Workshop board (jobs by stage), job sheet with its tasks, planning (priority, operator, machine, deadline, costs) and machines.
 */
final class ProductionController extends Controller
{
    private function userId(): int
    {
        return (int) (Auth::user()['id'] ?? 0);
    }

    public function index(): Response
    {
        $f = [
            'priority' => $this->request->str('priority'), 'user' => $this->request->int('user'),
            'machine' => $this->request->int('machine'), 'late' => $this->request->bool('late'),
        ];
        $db = Database::instance();
        return $this->view('admin/production/index', [
            'board' => ProductionService::board($f), 'f' => $f,
            'users' => $db->fetchAll('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name'),
            'machines' => $db->fetchAll("SELECT id, name FROM machines WHERE status <> 'inactive' ORDER BY name"),
            'waiting' => $db->fetchAll(
                "SELECT o.id, o.order_number, o.customer_name, o.status FROM orders o
                 WHERE o.status = ? AND NOT EXISTS (SELECT 1 FROM production_jobs j WHERE j.order_id = o.id AND j.status <> 'cancelled') ORDER BY o.placed_at",
                [OrderStatus::APPROVED_FOR_PRODUCTION]
            ),
            'done' => (int) $db->value("SELECT COUNT(*) FROM production_jobs WHERE status = 'completed' AND completed_at >= CURDATE()"),
        ]);
    }

    /** @return array<string,mixed> */
    private function job(string $id): array
    {
        $j = Database::instance()->fetch(
            'SELECT j.*, o.order_number, o.customer_name, o.status AS order_status, o.customer_notes, o.internal_notes AS order_notes,
                    oi.title, oi.quantity, oi.width_cm, oi.height_cm, oi.material_name, oi.frame_name, oi.panels, oi.options, oi.custom_design_id, oi.preview_path, oi.product_id
             FROM production_jobs j JOIN orders o ON o.id = j.order_id LEFT JOIN order_items oi ON oi.id = j.order_item_id WHERE j.id = ?',
            [(int) $id]
        );
        if ($j === null) {
            throw new HttpException(404);
        }
        return $j;
    }

    public function show(string $id): Response
    {
        $j = $this->job($id);
        $db = Database::instance();
        $item = $j['order_item_id'] ? $db->fetch('SELECT * FROM order_items WHERE id = ?', [(int) $j['order_item_id']]) : null;
        return $this->view('admin/production/show', [
            'j' => $j,
            'tasks' => $db->fetchAll('SELECT t.*, u.name AS user_name, m.name AS machine_name FROM production_tasks t LEFT JOIN users u ON u.id = t.assigned_user_id LEFT JOIN machines m ON m.id = t.machine_id WHERE t.production_job_id = ? ORDER BY t.sort_order', [(int) $j['id']]),
            'current' => ProductionService::currentTask((int) $j['id']),
            'users' => $db->fetchAll('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name'),
            'machines' => $db->fetchAll("SELECT id, name, hourly_cost FROM machines WHERE status <> 'inactive' ORDER BY name"),
            'requirements' => $item ? InventoryService::requirementsFor($item) : ['lines' => [], 'unlinked' => []],
            'materialNames' => array_column($db->fetchAll('SELECT id, name, unit, stock_quantity FROM materials'), null, 'id'),
            'consumed' => $db->fetchAll('SELECT s.*, m.name, m.unit FROM stock_movements s JOIN materials m ON m.id = s.material_id WHERE s.production_job_id = ? ORDER BY s.id', [(int) $j['id']]),
            'artworkPending' => $j['order_item_id'] ? (int) $db->value("SELECT COUNT(*) FROM order_customizations WHERE order_item_id = ? AND approval_status <> 'approved'", [(int) $j['order_item_id']]) : 0,
            'total' => ProductionService::totalCost($j),
        ]);
    }

    public function task(string $id, string $taskId): Response
    {
        $j = $this->job($id);
        $tid = (int) Database::instance()->value('SELECT id FROM production_tasks WHERE id = ? AND production_job_id = ?', [(int) $taskId, (int) $j['id']]);
        if ($tid === 0) {
            throw new HttpException(404);
        }
        try {
            ProductionService::act($tid, $this->request->str('action'), $this->userId(), mb_substr($this->request->str('notes'), 0, 500));
            $this->flash('success', __('production.task_saved'));
        } catch (ProductionException|WorkflowException $e) {
            $this->flash('danger', $e->getMessage());
        }
        $back = $this->request->str('back') === 'board' ? route('admin.production.index') : route('admin.production.show', ['id' => $j['id']]);
        return $this->redirect($back);
    }

    public function update(string $id): Response
    {
        $j = $this->job($id);
        $this->request->normalizeDecimals(['labor_cost', 'other_cost']);
        $d = $this->validate([
            'priority' => 'required|in:low,normal,high,urgent', 'assigned_user_id' => 'nullable|integer|exists:users,id',
            'machine_id' => 'nullable|integer|exists:machines,id', 'deadline_at' => 'nullable|date',
            'labor_cost' => 'nullable|decimal:2|min:0', 'other_cost' => 'nullable|decimal:2|min:0', 'notes' => 'nullable|max:5000',
        ]);
        ProductionService::updateJob((int) $j['id'], $d + ['hold' => $this->request->bool('hold')], $this->userId());
        $this->flash('success', __('common.saved'));
        return $this->redirectRoute('admin.production.show', ['id' => $j['id']]);
    }

    /** Create the jobs of an order approved for production (e.g. approved before jobs existed). */
    public function createJobs(string $orderId): Response
    {
        $o = Database::instance()->fetch('SELECT id, status FROM orders WHERE id = ?', [(int) $orderId]);
        if ($o === null) {
            throw new HttpException(404);
        }
        if (!in_array($o['status'], [OrderStatus::APPROVED_FOR_PRODUCTION, OrderStatus::PRINTING, OrderStatus::FRAMING, OrderStatus::QUALITY_CONTROL], true)) {
            $this->flash('danger', __('production.err_not_approved'));
        } else {
            $n = ProductionService::createJobsForOrder((int) $o['id'], $this->userId());
            $this->flash('success', __('production.jobs_created', ['n' => $n]));
        }
        return $this->redirectRoute('admin.production.index');
    }

    // ------------------------------------------------------------------ machines

    public function machines(): Response
    {
        return $this->view('admin/production/machines', ['machines' => Database::instance()->fetchAll(
            "SELECT m.*, (SELECT COUNT(*) FROM production_jobs j WHERE j.machine_id = m.id AND j.status IN ('pending','in_progress')) AS open_jobs FROM machines m ORDER BY m.status, m.name"
        )]);
    }

    public function saveMachine(?string $id = null): Response
    {
        $this->request->normalizeDecimals(['hourly_cost']);
        $d = $this->validate([
            'code' => 'required|alpha_dash|max:40|unique:machines,code' . ($id ? ',' . (int) $id : ''), 'name' => 'required|max:150',
            'machine_type' => 'required|max:60', 'status' => 'required|in:active,maintenance,inactive', 'hourly_cost' => 'required|decimal:2|min:0', 'notes' => 'nullable|max:2000',
        ]);
        $db = Database::instance();
        $row = ['code' => strtoupper((string) $d['code']), 'name' => $d['name'], 'machine_type' => $d['machine_type'], 'status' => $d['status'], 'hourly_cost' => $d['hourly_cost'], 'notes' => $d['notes']];
        if ($id === null) {
            $id = (string) $db->insert('machines', $row);
        } else {
            if ($db->value('SELECT id FROM machines WHERE id = ?', [(int) $id]) === null) {
                throw new HttpException(404);
            }
            $db->update('machines', $row, ['id' => (int) $id]);
        }
        ActivityLogger::log('machine.saved', 'machine', (int) $id, (string) $d['name']);
        $this->flash('success', __('common.saved'));
        return $this->redirect(route('admin.machines.index') . '#machine-' . (int) $id);
    }
}
