<?php
/**
 * Personalisation of an order / cart line (gifts, laser): name, message, font, date, photo…
 * Vars: $options (array or JSON string from order_items.options / cart line), $plain (bool: e-mail / PDF friendly).
 */
$opt = is_array($options ?? null) ? $options : (json_decode((string) ($options ?? ''), true) ?: []);
$perso = $opt['personalization'] ?? ($opt['items'] ?? []);
if (!is_array($perso) || $perso === []) {
    return;
}
$plain = !empty($plain);
?>
<?php if ($plain): ?>
    <div style="font-size:12px;color:#555;margin-top:2px">
        <?php foreach ($perso as $p): ?><div><strong><?= e((string) $p['label']) ?> :</strong> <span dir="auto"><?= e((string) $p['value']) ?></span></div><?php endforeach; ?>
    </div>
<?php else: ?>
    <ul class="perso-list">
        <?php foreach ($perso as $p): ?>
            <li><i class="bi <?= ($p['type'] ?? '') === 'photo' ? 'bi-image' : (($p['type'] ?? '') === 'font' ? 'bi-fonts' : 'bi-pencil') ?>"></i>
                <span class="perso-label"><?= e((string) $p['label']) ?></span>
                <span class="perso-value" dir="auto"><?= e((string) $p['value']) ?></span>
                <?php if (isset($p['dpi']) && $p['dpi'] !== null): ?><span class="badge-status <?= (int) $p['dpi'] < 100 ? 'is-danger' : 'is-muted' ?>"><?= (int) $p['dpi'] ?> DPI</span><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
