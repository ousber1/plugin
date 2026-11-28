<span class="stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $rating >= $i ? 'bi-star-fill' : ($rating >= $i - 0.5 ? 'bi-star-half' : 'bi-star') ?>"></i><?php endfor; ?></span>
