<?php
layout('layouts.app');
section('content');
$today = strtotime(date('Y-m-d'));
?>
<div class="page-head">
    <div>
        <h1>My Library</h1>
        <p class="lede">Search the catalogue, and keep track of what you have borrowed and when it is due back.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="mini-stat"><b><?= (int) $summary['on_loan'] ?></b><span>On loan now</span></div>
    </div>
    <div class="col-6 col-md-4">
        <div class="mini-stat <?= $summary['overdue'] > 0 ? 'text-danger' : '' ?>">
            <b><?= (int) $summary['overdue'] ?></b><span>Overdue</span></div>
    </div>
    <div class="col-12 col-md-4">
        <div class="mini-stat <?= $summary['fines'] > 0 ? 'text-danger' : '' ?>">
            <b><?= money($summary['fines']) ?></b><span>Unpaid fines</span></div>
    </div>
</div>

<?php if ($summary['overdue'] > 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2"><?= icon('bell') ?>
        <div>You have <strong><?= (int) $summary['overdue'] ?></strong> overdue
            book<?= $summary['overdue'] === 1 ? '' : 's' ?>. Please return
            <?= $summary['overdue'] === 1 ? 'it' : 'them' ?> to the library desk. Overdue books can hold up your clearance.</div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><?= icon('search') ?> Search the catalogue</div>
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label">Title, author, ISBN or accession number</label>
                <input type="search" name="q" class="form-control" value="<?= e($search) ?>"
                       placeholder="e.g. Principles of Accounting" autofocus>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
            <?php if ($search !== ''): ?>
                <div class="col-md-2"><a class="btn btn-light w-100" href="<?= url('/portal/library') ?>">Clear</a></div>
            <?php endif; ?>
        </form>

        <?php if ($search !== ''): ?>
            <hr>
            <?php if ($results === []): ?>
                <div class="empty-state py-4"><?= icon('book') ?>
                    <p class="mb-0">Nothing in the catalogue matches &ldquo;<?= e($search) ?>&rdquo;.</p></div>
            <?php else: ?>
                <p class="text-muted-sm"><?= count($results) ?> title<?= count($results) === 1 ? '' : 's' ?> found.
                    Ask at the library desk to borrow one.</p>
                <div class="table-wrap">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr><th>Title</th><th>Author</th><th>Category</th><th>Shelf</th><th class="text-center">Available</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($results as $b): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($b['title']) ?>
                                    <div class="text-muted-sm"><code><?= e($b['accession_number']) ?></code>
                                        <?= $b['publication_year'] ? ' &middot; ' . e((string) $b['publication_year']) : '' ?>
                                        <?= $b['publisher'] ? ' &middot; ' . e(str_limit((string) $b['publisher'], 30)) : '' ?></div></td>
                                <td><?= e((string) ($b['author'] ?? '')) ?></td>
                                <td class="text-muted-sm"><?= e((string) ($b['category'] ?? '')) ?></td>
                                <td class="text-muted-sm"><?= e((string) ($b['shelf_location'] ?? '')) ?></td>
                                <td class="text-center">
                                    <?php if ((int) $b['available_copies'] > 0): ?>
                                        <span class="badge text-bg-success"><?= (int) $b['available_copies'] ?> of <?= (int) $b['total_copies'] ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">All out</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center gap-2"><?= icon('library') ?> My borrowing
        <span class="badge text-bg-light"><?= count($loans) ?></span></div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Book</th><th>Author</th><th>Issued</th><th>Due back</th>
                <th>Status</th><th class="text-end">Fine</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($loans === []): ?>
                <tr><td colspan="6"><div class="empty-state"><?= icon('library') ?>
                    <p>You have not borrowed anything yet. Search the catalogue above to see what the library holds.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($loans as $l):
                $overdue = $l['returned_at'] === null && $l['due_date'] !== null && strtotime((string) $l['due_date']) < $today;
                ?>
                <tr class="<?= $overdue ? 'table-warning' : '' ?>">
                    <td class="fw-semibold"><?= e($l['title']) ?>
                        <div class="text-muted-sm"><code><?= e($l['accession_number']) ?></code></div></td>
                    <td><?= e((string) ($l['author'] ?? '')) ?></td>
                    <td class="text-muted-sm"><?= date_fmt($l['issued_at']) ?></td>
                    <td class="<?= $overdue ? 'fw-semibold text-danger' : '' ?>">
                        <?= date_fmt($l['due_date']) ?>
                        <?php if ($overdue): ?>
                            <div class="text-muted-sm text-danger"><?= (int) floor(($today - strtotime((string) $l['due_date'])) / 86400) ?> day(s) late</div>
                        <?php elseif ($l['returned_at'] === null && $l['due_date'] !== null): ?>
                            <div class="text-muted-sm">in <?= e(time_until($l['due_date'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($l['returned_at'] !== null): ?>
                            <span class="badge text-bg-secondary">Returned <?= date_fmt($l['returned_at']) ?></span>
                        <?php elseif ($overdue): ?>
                            <span class="badge text-bg-danger">Overdue</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">On loan</span>
                        <?php endif; ?>
                        <?php if ((int) $l['renewals'] > 0): ?>
                            <span class="text-muted-sm">renewed <?= (int) $l['renewals'] ?>&times;</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ((float) $l['fine_amount'] > 0): ?>
                            <span class="<?= (int) $l['fine_paid'] === 1 ? 'text-muted' : 'fw-semibold text-danger' ?>">
                                <?= money($l['fine_amount']) ?></span>
                            <div class="text-muted-sm"><?= (int) $l['fine_paid'] === 1 ? 'paid' : 'unpaid' ?></div>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
