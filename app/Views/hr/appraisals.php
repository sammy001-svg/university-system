<?php
layout('layouts.app');
section('content');
$current = null;
foreach ($cycles as $c) { if ((int) $c['id'] === $cycleId) { $current = $c; } }
?>
<div class="page-head">
    <div><h1>Staff Appraisal</h1><p class="lede">Annual performance appraisal: self-assessment, appraiser review and acknowledgement.</p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/appraisals/mine') ?>">My appraisal</a>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <?php if ($current): ?>
            <div class="row g-3 mb-3">
                <?php foreach ([['Staff', (int) $current['total']], ['Not started', (int) $current['pending']], ['Awaiting review', (int) $current['awaiting_review']],
                                ['Completed', (int) $current['done']], ['Average rating', $current['average'] ? number_format((float) $current['average'], 2) . ' / 5' : '&ndash;']] as [$l, $v]): ?>
                    <div class="col"><div class="mini-stat"><b><?= $v ?></b><span><?= e($l) ?></span></div></div>
                <?php endforeach; ?>
            </div>
            <div class="card">
                <div class="card-header"><?= icon('users') ?> <?= e($current['title']) ?> &middot; <?= fdate($current['period_start']) ?> &ndash; <?= fdate($current['period_end']) ?>
                    <span class="ms-2"><?= status_badge($current['status']) ?></span>
                    <?php if ($canManage): ?>
                        <form class="ms-auto" method="post" action="<?= url('/hr/appraisal-cycles/' . $current['id'] . '/toggle') ?>"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-link p-0"><?= $current['status'] === 'open' ? 'Close cycle' : 'Reopen cycle' ?></button></form>
                    <?php endif; ?>
                </div>
                <div class="table-wrap"><table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Staff member</th><th>Appraiser</th><th>Status</th><th class="text-end">Rating</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e($r['staff_name']) ?></div><div class="text-muted-sm"><?= e((string) $r['designation']) ?> &middot; <?= e($r['staff_number']) ?></div></td>
                            <td style="min-width:240px">
                                <?php if ($canManage && $r['status'] !== 'acknowledged'): ?>
                                    <form method="post" action="<?= url('/appraisals/' . $r['id'] . '/reviewer') ?>" class="d-flex gap-1"><?= csrf_field() ?>
                                        <select class="form-select form-select-sm" name="reviewer_id"><?= options($staff, $r['reviewer_id'], 'Not assigned') ?></select>
                                        <button class="btn btn-sm btn-light">Set</button></form>
                                <?php else: ?><?= e((string) ($r['reviewer_name'] ?? 'Not assigned')) ?><?php endif; ?>
                            </td>
                            <td><?= status_badge(['pending' => 'pending', 'self_submitted' => 'submitted', 'reviewed' => 'approved', 'acknowledged' => 'completed'][$r['status']]) ?></td>
                            <td class="text-end fw-semibold"><?= $r['overall_rating'] !== null ? number_format((float) $r['overall_rating'], 2) : '' ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-light" href="<?= url('/appraisals/' . $r['id']) ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
            </div>
        <?php else: ?>
            <div class="card"><div class="card-body"><div class="empty-state"><?= icon('users') ?><p>No appraisal cycle yet.</p></div></div></div>
        <?php endif; ?>
    </div>
    <div class="col-xl-3">
        <?php if ($cycles !== []): ?>
            <div class="card mb-3"><div class="card-header">Cycles</div><div class="list-group list-group-flush">
                <?php foreach ($cycles as $c): ?>
                    <a class="list-group-item list-group-item-action <?= (int) $c['id'] === $cycleId ? 'active' : '' ?>" href="<?= url('/hr/appraisals?cycle=' . $c['id']) ?>">
                        <?= e($c['title']) ?><div class="small opacity-75"><?= (int) $c['done'] ?> / <?= (int) $c['total'] ?> done</div></a>
                <?php endforeach; ?>
            </div></div>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <div class="card">
                <div class="card-header"><?= icon('plus') ?> Open a new cycle</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/hr/appraisal-cycles') ?>"><?= csrf_field() ?>
                        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required placeholder="e.g. Annual Appraisal <?= date('Y') ?>"></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">From</label><input class="form-control" type="date" name="period_start" value="<?= date('Y') ?>-01-01" required></div>
                            <div class="col-6"><label class="form-label">To</label><input class="form-control" type="date" name="period_end" value="<?= date('Y') ?>-12-31" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Self-assessments due</label><input class="form-control" type="date" name="due_date"></div>
                        <button class="btn btn-primary w-100">Open for all active staff</button>
                        <div class="form-text">Each person's college dean is set as their appraiser; change it in the list.</div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
