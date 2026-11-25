<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Pagination SQL (LIMIT/OFFSET) + rendu des liens.
 *
 *   $p = Paginator::fromRequest($total);
 *   $rows = $db->fetchAll($sql . ' LIMIT ' . $p->limit() . ' OFFSET ' . $p->offset(), $params);
 *   View::partial('pagination', ['p' => $p]);
 */
final class Paginator
{
    public function __construct(
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $page
    ) {
    }

    public static function fromRequest(int $total, ?int $perPage = null): self
    {
        $perPage ??= (int) Settings::get('items_per_page', 25);
        $perPage = max(5, min(200, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, Request::int('page', 1)));
        return new self($total, $perPage, $page);
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function limit(): int
    {
        return $this->perPage;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    /** URL de la page $n en conservant les filtres de la requête courante. */
    public function url(int $n): string
    {
        $query = $_GET;
        unset($query['route']);
        $query['page'] = $n;
        return url(Request::route(), $query);
    }

    /** Numéros de pages à afficher (avec null pour « … »). */
    public function window(int $around = 2): array
    {
        $pages = $this->pages();
        $out = [];
        for ($i = 1; $i <= $pages; $i++) {
            if ($i === 1 || $i === $pages || abs($i - $this->page) <= $around) {
                $out[] = $i;
            } elseif (end($out) !== null) {
                $out[] = null;
            }
        }
        return $out;
    }
}
