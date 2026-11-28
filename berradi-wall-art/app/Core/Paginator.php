<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    /** @param list<array<string,mixed>> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->currentPage * $this->perPage);
    }

    /** Build URL for a page, keeping current query string. */
    public function url(int $page): string
    {
        $query = $_GET;
        $query['page'] = $page;
        $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
        return $path . '?' . http_build_query($query);
    }

    /** Bootstrap 5 pagination markup. */
    public function links(): string
    {
        if (!$this->hasPages()) {
            return '';
        }
        $last = $this->lastPage();
        $cur = $this->currentPage;
        $html = '<nav aria-label="pagination"><ul class="pagination pagination-sm mb-0">';
        $html .= $this->item($cur - 1, '&lsaquo;', $cur <= 1);
        $window = array_unique(array_filter([1, $cur - 2, $cur - 1, $cur, $cur + 1, $cur + 2, $last], fn ($p) => $p >= 1 && $p <= $last));
        sort($window);
        $prev = 0;
        foreach ($window as $p) {
            if ($p - $prev > 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
            $html .= $this->item($p, (string) $p, false, $p === $cur);
            $prev = $p;
        }
        $html .= $this->item($cur + 1, '&rsaquo;', $cur >= $last);
        return $html . '</ul></nav>';
    }

    private function item(int $page, string $label, bool $disabled, bool $active = false): string
    {
        $cls = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
        if ($disabled || $active) {
            return '<li class="' . $cls . '"><span class="page-link">' . $label . '</span></li>';
        }
        return '<li class="' . $cls . '"><a class="page-link" href="' . htmlspecialchars($this->url($page), ENT_QUOTES) . '">' . $label . '</a></li>';
    }
}
