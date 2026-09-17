<?php /** @var Paginator $page */ if ($page->pages() <= 1 && $page->total <= $page->per_page) { echo '<div class="text-muted small" data-testid="pagination-info">' . $page->total . ' data</div>'; return; } ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
    <div class="text-muted small" data-testid="pagination-info">Menampilkan <?= ($page->page - 1) * $page->per_page + 1 ?>–<?= min($page->total, $page->page * $page->per_page) ?> dari <?= number_format($page->total) ?></div>
    <nav><ul class="pagination pagination-sm mb-0" data-testid="pagination">
        <li class="page-item <?= $page->page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => $page->page - 1])) ?>">&laquo;</a></li>
        <?php for ($p = max(1, $page->page - 3); $p <= min($page->pages(), $page->page + 3); $p++): ?>
            <li class="page-item <?= $p === $page->page ? 'active' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => $p])) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page->page >= $page->pages() ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => $page->page + 1])) ?>">&raquo;</a></li>
    </ul></nav>
</div>
