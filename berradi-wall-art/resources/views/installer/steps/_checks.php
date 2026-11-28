<div class="table-responsive mb-4">
    <table class="table check-table align-middle">
        <thead><tr><th><?= e(__('installer.check')) ?></th><th><?= e(__('installer.required')) ?></th><th><?= e(__('installer.current')) ?></th><th class="text-end"><?= e(__('admin.status')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><?= e($r['label']) ?></td>
                <td class="text-muted-bwa"><?= e($r['required']) ?></td>
                <td dir="ltr" class="text-start"><?= e($r['current']) ?></td>
                <td class="text-end">
                    <?php if ($r['ok']): ?><i class="bi bi-check-circle-fill status-ok"></i>
                    <?php elseif ($r['critical']): ?><i class="bi bi-x-circle-fill status-bad"></i>
                    <?php else: ?><i class="bi bi-exclamation-triangle-fill status-warn"></i><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
