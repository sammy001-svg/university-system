<?php
layout('layouts.app');
section('content');
$bar = static function (?float $avg): string {
    if ($avg === null) { return '<span class="text-muted-sm">&ndash;</span>'; }
    $pct = ($avg - 1) / 4 * 100;
    return '<div class="d-flex align-items-center gap-2"><div class="meter flex-fill ' . ($avg >= 4 ? 'ok' : ($avg >= 3 ? '' : 'bad')) . '" style="max-width:120px"><span style="width:' . round($pct) . '%"></span></div><b>' . number_format($avg, 2) . '</b></div>';
};
?>
<div class="page-head">
    <div><h1>Course Evaluations</h1><p class="lede">Students' anonymous evaluation of courses and teaching, each semester. Averages are on a 1-5 scale (5 = strongly agree).</p></div>
</div>
<div class="row g-3">
    <div class="col-xl-9">
        <?php if ($period): ?>
            <div class="card">
                <div class="card-header"><?= icon('chart') ?> <?= e($period['title']) ?> &middot; <?= fdatetime($period['opens_at']) ?> &ndash; <?= fdatetime($period['closes_at']) ?>
                    <span class="ms-2"><?= status_badge($period['status']) ?></span>
                    <?php if ($canManage): ?>
                        <form class="ms-auto" method="post" action="<?= url('/evaluations/periods/' . $period['id'] . '/toggle') ?>"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-link p-0"><?= $period['status'] === 'open' ? 'Close now' : 'Reopen' ?></button></form>
                    <?php endif; ?>
                </div>
                <div class="table-wrap"><table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Course</th><th>Lecturer</th><th class="text-center">Responses</th><th>Average</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($rows === []): ?><tr><td colspan="5"><div class="empty-state py-4"><p>No courses with registered students in this semester.</p></div></td></tr><?php endif; ?>
                    <?php foreach ($rows as $r): $shown = (int) $r['responses'] >= (int) $r['min_responses']; ?>
                        <tr>
                            <td><b><?= e($r['code']) ?></b> <?= e($r['section']) ?><div class="text-muted-sm"><?= e($r['title']) ?> &middot; <?= e((string) $r['department_name']) ?></div></td>
                            <td class="small"><?= e((string) ($r['lecturer_name'] ?? 'Not assigned')) ?></td>
                            <td class="text-center"><?= (int) $r['responses'] ?> / <?= (int) $r['enrolled'] ?>
                                <div class="text-muted-sm"><?= (int) $r['enrolled'] ? round((int) $r['responses'] / (int) $r['enrolled'] * 100) : 0 ?>%</div></td>
                            <td><?= $shown ? $bar($r['average'] !== null ? (float) $r['average'] : null) : '<span class="text-muted-sm">Fewer than ' . (int) $r['min_responses'] . ' responses</span>' ?></td>
                            <td class="text-end"><?php if ($shown): ?><a class="btn btn-sm btn-light" href="<?= url('/evaluations/' . $r['period_id'] . '/' . $r['offering_id']) ?>">Details</a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
            </div>
        <?php else: ?>
            <div class="card"><div class="card-body"><div class="empty-state"><?= icon('chart') ?><p>No evaluation has been run yet.</p></div></div></div>
        <?php endif; ?>
    </div>
    <div class="col-xl-3">
        <?php if ($periods !== []): ?>
            <div class="card mb-3"><div class="card-header">Evaluations</div><div class="list-group list-group-flush">
                <?php foreach ($periods as $p): ?>
                    <a class="list-group-item list-group-item-action <?= $period && (int) $p['id'] === (int) $period['id'] ? 'active' : '' ?>" href="<?= url('/evaluations?period=' . $p['id']) ?>">
                        <?= e($p['title']) ?><div class="small opacity-75"><?= e($p['semester_label']) ?> &middot; <?= (int) $p['responses'] ?> responses</div></a>
                <?php endforeach; ?>
            </div></div>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <div class="card"><div class="card-header"><?= icon('plus') ?> Open an evaluation</div><div class="card-body">
                <form method="post" action="<?= url('/evaluations/periods') ?>"><?= csrf_field() ?>
                    <div class="mb-2"><label class="form-label">Semester</label><select class="form-select" name="semester_id" required><?= options($semesters) ?></select></div>
                    <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required placeholder="e.g. End of Semester Evaluation"></div>
                    <div class="mb-2"><label class="form-label">Opens</label><input class="form-control" type="datetime-local" name="opens_at" value="<?= date('Y-m-d\TH:i') ?>" required></div>
                    <div class="mb-2"><label class="form-label">Closes</label><input class="form-control" type="datetime-local" name="closes_at" value="<?= date('Y-m-d\TH:i', strtotime('+14 days')) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Hide results below (responses)</label><input class="form-control" type="number" name="min_responses" value="3" min="1" max="50"></div>
                    <button class="btn btn-primary w-100">Open and invite students</button>
                </form></div></div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
