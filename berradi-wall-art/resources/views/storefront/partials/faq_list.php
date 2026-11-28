<div class="accordion faq-accordion" id="<?= e($id) ?>">
    <?php foreach ($faqs as $i => $f): ?>
        <div class="accordion-item">
            <h3 class="accordion-header">
                <button class="accordion-button<?= $i === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($id) ?>-<?= (int) $f['id'] ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                    <?= e($f['question']) ?>
                </button>
            </h3>
            <div id="<?= e($id) ?>-<?= (int) $f['id'] ?>" class="accordion-collapse collapse<?= $i === 0 ? ' show' : '' ?>" data-bs-parent="#<?= e($id) ?>">
                <div class="accordion-body"><?= nl2br(e($f['answer'])) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
