<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>My Clearance Status</h1>
        <p class="lede">Departmental clearance progress for graduation &amp; leave.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('clearance') ?> Department Sign-offs</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Signed Date</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="4"><div class="empty-state"><?= icon('clearance') ?><p>No clearance items initiated.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="fw-semibold"><?= e(humanize($item['department'])) ?></td>
                    <td><?= status_badge($item['status']) ?></td>
                    <td class="text-muted-sm"><?= date_fmt($item['signed_at'] ?? '') ?></td>
                    <td class="text-muted-sm"><?= e($item['remarks'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
