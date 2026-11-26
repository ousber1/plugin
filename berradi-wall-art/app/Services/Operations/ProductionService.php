<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Core\Database;
use App\Helpers\Money;
use App\Services\ActivityLogger;
use App\Services\Sales\OrderStatus;
use App\Services\Sales\OrderWorkflow;
use App\Services\SequenceService;

/**
 * Workshop: one production job per order line, one task per stage, worked in order.
 * Finishing the print stage books the material consumption; progress pushes the order status forward.
 */
final class ProductionService
{
    public const STAGES = ['receive', 'review_quality', 'approve_artwork', 'prepare_file', 'print', 'cut_finish', 'frame', 'quality_control', 'package', 'ship'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** Order status reached when the slowest/fastest job is at a stage. */
    private const STAGE_TO_ORDER = [
        'print' => OrderStatus::PRINTING, 'cut_finish' => OrderStatus::PRINTING, 'frame' => OrderStatus::FRAMING,
        'quality_control' => OrderStatus::QUALITY_CONTROL, 'package' => OrderStatus::QUALITY_CONTROL,
    ];

    /** Stages that apply to an order line. @param array<string,mixed> $item @return list<string> */
    public static function stagesFor(array $item): array
    {
        return array_values(array_filter(self::STAGES, function (string $s) use ($item): bool {
            return match ($s) {
                'review_quality', 'approve_artwork' => !empty($item['custom_design_id']),
                'frame' => !empty($item['frame_name']),
                default => true,
            };
        }));
    }

    /** Create the jobs of an order (once). @return int jobs created */
    public static function createJobsForOrder(int $orderId, ?int $userId = null): int
    {
        $db = Database::instance();
        if ((int) $db->value("SELECT COUNT(*) FROM production_jobs WHERE order_id = ? AND status <> 'cancelled'", [$orderId]) > 0) {
            return 0;
        }
        $order = $db->fetch('SELECT id, order_number, estimated_delivery_min FROM orders WHERE id = ?', [$orderId]);
        if ($order === null) {
            return 0;
        }
        $days = max(1, (int) setting('production_lead_days', 3));
        $deadline = $order['estimated_delivery_min'] !== null
            ? date('Y-m-d 18:00:00', max(time() + 86400, strtotime($order['estimated_delivery_min'] . ' -1 day')))
            : date('Y-m-d 18:00:00', strtotime('+' . $days . ' days'));
        $n = 0;
        $db->transaction(function (Database $db) use ($orderId, $userId, $deadline, &$n): void {
            foreach ($db->fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]) as $item) {
                $stages = self::stagesFor($item);
                $approved = !empty($item['custom_design_id'])
                    && (int) $db->value("SELECT COUNT(*) FROM order_customizations WHERE order_item_id = ? AND approval_status <> 'approved'", [(int) $item['id']]) === 0;
                $jobId = $db->insert('production_jobs', [
                    'job_number' => SequenceService::next('production'), 'order_id' => $orderId, 'order_item_id' => (int) $item['id'],
                    'status' => 'pending', 'current_stage' => 'receive', 'priority' => 'normal', 'deadline_at' => $deadline, 'created_by' => $userId,
                ]);
                foreach ($stages as $i => $stage) {
                    // the order is received by definition; artwork already validated needs no second approval
                    $done = $stage === 'receive' || ($approved && in_array($stage, ['review_quality', 'approve_artwork'], true));
                    $db->insert('production_tasks', [
                        'production_job_id' => $jobId, 'stage' => $stage, 'status' => $done ? 'done' : 'pending', 'sort_order' => $i,
                        'completed_at' => $done ? date('Y-m-d H:i:s') : null, 'notes' => $done && $stage !== 'receive' ? 'Visuel déjà approuvé' : null,
                    ]);
                }
                self::refreshJob($db, $jobId);
                $n++;
            }
        });
        ActivityLogger::log('production.jobs_created', 'order', $orderId, (string) $n);
        return $n;
    }

    /** First task still to do in a job. @return array<string,mixed>|null */
    public static function currentTask(int $jobId): ?array
    {
        return Database::instance()->fetch("SELECT * FROM production_tasks WHERE production_job_id = ? AND status IN ('pending','in_progress') ORDER BY sort_order LIMIT 1", [$jobId]);
    }

    /** Start / finish / skip the current task of a job. */
    public static function act(int $taskId, string $action, ?int $userId = null, string $notes = ''): void
    {
        if (!in_array($action, ['start', 'done', 'skip'], true)) {
            throw new ProductionException(__('production.err_action'));
        }
        $db = Database::instance();
        $orderId = $db->transaction(function (Database $db) use ($taskId, $action, $userId, $notes): int {
            $t = $db->fetch('SELECT t.*, j.status AS job_status, j.order_id FROM production_tasks t JOIN production_jobs j ON j.id = t.production_job_id WHERE t.id = ? FOR UPDATE', [$taskId]);
            if ($t === null) {
                throw new ProductionException(__('production.err_task'));
            }
            if (in_array($t['job_status'], ['completed', 'cancelled', 'on_hold'], true)) {
                throw new ProductionException(__('production.err_job_closed'));
            }
            $current = self::currentTask((int) $t['production_job_id']);
            if ($current === null || (int) $current['id'] !== $taskId) {
                throw new ProductionException(__('production.err_order'));
            }
            if ($t['stage'] === 'ship' && $action !== 'start') {
                // shipping is closed by the shipment (order marked as shipped)
                $status = (string) $db->value('SELECT status FROM orders WHERE id = ?', [(int) $t['order_id']]);
                if (!in_array($status, [OrderStatus::SHIPPED, OrderStatus::DELIVERED], true)) {
                    throw new ProductionException(__('production.err_ship'));
                }
            }
            if ($t['stage'] === 'approve_artwork' && $action === 'done') {
                $pending = (int) $db->value(
                    "SELECT COUNT(*) FROM order_customizations oc JOIN production_jobs j ON j.order_item_id = oc.order_item_id WHERE j.id = ? AND oc.approval_status <> 'approved'",
                    [(int) $t['production_job_id']]
                );
                if ($pending > 0) {
                    throw new ProductionException(__('production.err_artwork'));
                }
            }
            $now = date('Y-m-d H:i:s');
            $values = ['assigned_user_id' => $t['assigned_user_id'] ?? $userId];
            if ($notes !== '') {
                $values['notes'] = mb_substr(trim(($t['notes'] ? $t['notes'] . "\n" : '') . $notes), 0, 1000);
            }
            if ($action === 'start') {
                if ($t['status'] === 'in_progress') {
                    throw new ProductionException(__('production.err_started'));
                }
                $values += ['status' => 'in_progress', 'started_at' => $now];
                $db->query("UPDATE production_jobs SET status = 'in_progress', started_at = COALESCE(started_at, ?) WHERE id = ?", [$now, (int) $t['production_job_id']]);
            } else {
                $started = $t['started_at'] !== null ? strtotime((string) $t['started_at']) : null;
                $minutes = $started !== null ? max(1, (int) round((time() - $started) / 60)) : null;
                $values += ['status' => $action === 'done' ? 'done' : 'skipped', 'completed_at' => $now, 'duration_minutes' => $action === 'done' ? $minutes : null];
                if ($action === 'done' && $minutes !== null && $t['machine_id'] !== null) {
                    $rate = (string) $db->value('SELECT hourly_cost FROM machines WHERE id = ?', [(int) $t['machine_id']]);
                    $cost = Money::fromCents(intdiv(Money::toCents($rate) * $minutes + 30, 60));
                    $db->query('UPDATE production_jobs SET machine_cost = machine_cost + ? WHERE id = ?', [$cost, (int) $t['production_job_id']]);
                }
            }
            $db->update('production_tasks', $values, ['id' => $taskId]);
            if ($action === 'done' && $t['stage'] === 'print') {
                InventoryService::consumeForJob((int) $t['production_job_id'], $userId);
            }
            self::refreshJob($db, (int) $t['production_job_id']);
            return (int) $t['order_id'];
        });
        ActivityLogger::log('production.task_' . $action, 'production_task', $taskId);
        self::syncOrder($orderId, $userId);
    }

    /** Close a stage on every job of an order (artwork approved, order shipped…). */
    public static function completeStage(int $orderId, string $stage): void
    {
        $db = Database::instance();
        $jobs = $db->fetchAll("SELECT id FROM production_jobs WHERE order_id = ? AND status <> 'cancelled'", [$orderId]);
        foreach ($jobs as $j) {
            $db->query(
                "UPDATE production_tasks SET status = 'done', completed_at = NOW() WHERE production_job_id = ? AND stage = ? AND status IN ('pending','in_progress')",
                [(int) $j['id'], $stage]
            );
            if ($stage === 'ship') {
                // a shipped order has been packed: close what the workshop forgot to tick
                $db->query("UPDATE production_tasks SET status = 'done', completed_at = NOW() WHERE production_job_id = ? AND stage = 'package' AND status IN ('pending','in_progress')", [(int) $j['id']]);
            }
            self::refreshJob($db, (int) $j['id']);
        }
    }

    /** Recompute current stage / status / completion of a job from its tasks. */
    public static function refreshJob(Database $db, int $jobId): void
    {
        $job = $db->fetch('SELECT status FROM production_jobs WHERE id = ?', [$jobId]);
        if ($job === null || $job['status'] === 'cancelled') {
            return;
        }
        $next = $db->fetch("SELECT stage FROM production_tasks WHERE production_job_id = ? AND status IN ('pending','in_progress') ORDER BY sort_order LIMIT 1", [$jobId]);
        if ($next === null) {
            $db->query("UPDATE production_jobs SET status = 'completed', current_stage = 'ship', completed_at = COALESCE(completed_at, NOW()) WHERE id = ?", [$jobId]);
            return;
        }
        $db->update('production_jobs', ['current_stage' => $next['stage']] + ($job['status'] === 'completed' ? ['status' => 'in_progress', 'completed_at' => null] : []), ['id' => $jobId]);
    }

    /** Push the order status forward from workshop progress. */
    public static function syncOrder(int $orderId, ?int $userId = null): void
    {
        $stages = array_column(Database::instance()->fetchAll(
            "SELECT current_stage, status FROM production_jobs WHERE order_id = ? AND status <> 'cancelled'",
            [$orderId]
        ), 'current_stage');
        if ($stages === []) {
            return;
        }
        $idx = array_map(fn ($s) => (int) array_search($s, self::STAGES, true), $stages);
        if (min($idx) >= array_search('ship', self::STAGES, true)) {
            OrderWorkflow::advanceProduction($orderId, OrderStatus::READY_FOR_SHIPPING, $userId);
            return;
        }
        $furthest = self::STAGES[max($idx)];
        if (isset(self::STAGE_TO_ORDER[$furthest])) {
            OrderWorkflow::advanceProduction($orderId, self::STAGE_TO_ORDER[$furthest], $userId);
        }
    }

    /**
     * Edit planning fields of a job.
     *
     * @param array<string,mixed> $d
     */
    public static function updateJob(int $jobId, array $d, ?int $userId = null): void
    {
        $db = Database::instance();
        $job = $db->fetch('SELECT * FROM production_jobs WHERE id = ?', [$jobId]);
        if ($job === null) {
            throw new ProductionException(__('production.err_task'));
        }
        $values = [];
        if (isset($d['priority']) && in_array($d['priority'], self::PRIORITIES, true)) {
            $values['priority'] = $d['priority'];
        }
        foreach (['assigned_user_id', 'machine_id'] as $k) {
            if (array_key_exists($k, $d)) {
                $values[$k] = $d[$k] !== null && $d[$k] !== '' ? (int) $d[$k] : null;
            }
        }
        if (array_key_exists('deadline_at', $d)) {
            $values['deadline_at'] = $d['deadline_at'] ? date('Y-m-d H:i:s', (int) strtotime((string) $d['deadline_at'])) : null;
        }
        foreach (['labor_cost', 'other_cost'] as $k) {
            if (isset($d[$k]) && $d[$k] !== '') {
                $values[$k] = Money::normalize((string) $d[$k]);
            }
        }
        if (array_key_exists('notes', $d)) {
            $values['notes'] = $d['notes'] !== null && $d['notes'] !== '' ? mb_substr((string) $d['notes'], 0, 5000) : null;
        }
        if (isset($d['hold'])) {
            if ($d['hold'] && in_array($job['status'], ['pending', 'in_progress'], true)) {
                $values['status'] = 'on_hold';
            } elseif (!$d['hold'] && $job['status'] === 'on_hold') {
                $values['status'] = $job['started_at'] !== null ? 'in_progress' : 'pending';
            }
        }
        if ($values !== []) {
            $db->update('production_jobs', $values, ['id' => $jobId]);
            if (array_key_exists('machine_id', $values)) {
                // the machine applies to the stages still to come
                $db->query("UPDATE production_tasks SET machine_id = ? WHERE production_job_id = ? AND status IN ('pending','in_progress') AND stage IN ('print','cut_finish')", [$values['machine_id'], $jobId]);
            }
            ActivityLogger::log('production.job_updated', 'production_job', $jobId, implode(',', array_keys($values)));
        }
    }

    /** Total cost of a job (material + labour + machine + other). @param array<string,mixed> $job */
    public static function totalCost(array $job): string
    {
        return Money::add((string) $job['material_cost'], (string) $job['labor_cost'], (string) $job['machine_cost'], (string) $job['other_cost']);
    }

    /**
     * Open jobs for the board, grouped by current stage.
     *
     * @param array{priority?:string,user?:int,machine?:int,late?:bool} $f
     * @return array<string,list<array<string,mixed>>>
     */
    public static function board(array $f = []): array
    {
        $where = ["j.status IN ('pending','in_progress','on_hold')", "o.status NOT IN ('cancelled','returned')"];
        $params = [];
        if (!empty($f['priority']) && in_array($f['priority'], self::PRIORITIES, true)) {
            $where[] = 'j.priority = ?';
            $params[] = $f['priority'];
        }
        if (!empty($f['user'])) {
            $where[] = 'j.assigned_user_id = ?';
            $params[] = (int) $f['user'];
        }
        if (!empty($f['machine'])) {
            $where[] = 'j.machine_id = ?';
            $params[] = (int) $f['machine'];
        }
        if (!empty($f['late'])) {
            $where[] = 'j.deadline_at < NOW()';
        }
        $rows = Database::instance()->fetchAll(
            "SELECT j.*, o.order_number, o.customer_name, o.status AS order_status, oi.title, oi.quantity, oi.width_cm, oi.height_cm, oi.material_name, oi.frame_name,
                    oi.panels, oi.custom_design_id, u.name AS assignee, m.name AS machine_name,
                    (SELECT t.id FROM production_tasks t WHERE t.production_job_id = j.id AND t.status IN ('pending','in_progress') ORDER BY t.sort_order LIMIT 1) AS task_id,
                    (SELECT t.status FROM production_tasks t WHERE t.production_job_id = j.id AND t.status IN ('pending','in_progress') ORDER BY t.sort_order LIMIT 1) AS task_status
             FROM production_jobs j
             JOIN orders o ON o.id = j.order_id
             LEFT JOIN order_items oi ON oi.id = j.order_item_id
             LEFT JOIN users u ON u.id = j.assigned_user_id
             LEFT JOIN machines m ON m.id = j.machine_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY FIELD(j.priority, 'urgent','high','normal','low'), j.deadline_at IS NULL, j.deadline_at, j.id",
            $params
        );
        $out = array_fill_keys(self::STAGES, []);
        foreach ($rows as $r) {
            $out[$r['current_stage']][] = $r;
        }
        return $out;
    }
}
