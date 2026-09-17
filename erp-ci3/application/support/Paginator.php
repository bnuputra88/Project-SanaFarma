<?php
/**
 * Server-side pagination result (consistent for web tables and API).
 */
final class Paginator
{
    public $items;
    public $total;
    public $page;
    public $per_page;

    public function __construct(array $items, int $total, int $page, int $perPage)
    {
        $this->items = $items;
        $this->total = $total;
        $this->page = max(1, $page);
        $this->per_page = max(1, $perPage);
    }

    public function pages(): int
    {
        return (int) max(1, ceil($this->total / $this->per_page));
    }

    public function toArray(): array
    {
        return ['items' => $this->items, 'meta' => ['total' => $this->total, 'page' => $this->page, 'per_page' => $this->per_page, 'pages' => $this->pages()]];
    }

    public static function fromInput(array $input, int $defaultPerPage = 25, int $maxPerPage = 200): array
    {
        $page = max(1, (int) ($input['page'] ?? 1));
        $per = min($maxPerPage, max(1, (int) ($input['per_page'] ?? $defaultPerPage)));
        return [$page, $per, ($page - 1) * $per];
    }
}
