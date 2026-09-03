<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Library Book Loans</h1>
        <p class="lede">Book circulation, active loans &amp; returns.</p>
    </div>
    <?php if (can('library.issue')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/library/loans/issue') ?>">
            <?= icon('plus', 'ico-sm') ?> Issue Book
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('library') ?> Book Loans (<?= count($loans) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Accession No.</th>
                    <th>Book Title</th>
                    <th>Borrower</th>
                    <th>Issued Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($loans)): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('library') ?><p>No active loans.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($loans as $l): ?>
                <tr>
                    <td><code><?= e($l['accession_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($l['title']) ?></td>
                    <td><?= e($l['first_name'] . ' ' . $l['last_name']) ?> (<code><?= e($l['admission_number']) ?></code>)</td>
                    <td><?= date_fmt($l['issued_date']) ?></td>
                    <td><?= date_fmt($l['due_date']) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($l['status'] === 'borrowed' && can('library.issue')): ?>
                            <form method="post" action="<?= url('/library/loans/' . $l['id'] . '/renew') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary">Renew</button>
                            </form>
                            <form method="post" action="<?= url('/library/loans/' . $l['id'] . '/return') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-success">Receive Book</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
