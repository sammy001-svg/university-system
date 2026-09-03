<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>My Library Loans</h1>
        <p class="lede">Active and past book loans.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('library') ?> Issued Books</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Accession No.</th>
                    <th>Book Title</th>
                    <th>Author</th>
                    <th>Issued Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($loans)): ?>
                <tr><td colspan="6"><div class="empty-state"><?= icon('library') ?><p>No book loans recorded.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($loans as $l): ?>
                <tr>
                    <td><code><?= e($l['accession_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($l['title']) ?></td>
                    <td><?= e($l['author']) ?></td>
                    <td><?= date_fmt($l['issued_date']) ?></td>
                    <td><?= date_fmt($l['due_date']) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
