<?php
layout('layouts.app');
section('content');
$a   = $assessment;
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$entered = count(array_filter($students, static fn ($s) => $s['score'] !== null));
?>
<div class="page-head">
    <div>
        <h1><?= e($a['title']) ?></h1>
        <p class="lede"><?= e($a['code']) ?> &middot; marked out of <?= $fmt($a['max_score']) ?> &middot; weight <?= $fmt($a['weight']) ?>% &middot;
            <?= $entered ?> of <?= count($students) ?> scores entered</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $a['offering_id'] . '/assessments') ?>">&larr; Assessments</a>
</div>
<form method="post" action="<?= url('/teaching/assessments/' . $a['id'] . '/scores') ?>" class="card" style="max-width:820px">
    <?= csrf_field() ?>
    <div class="table-wrap"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Student ID</th><th>Name</th><th style="width:160px">Score / <?= $fmt($a['max_score']) ?></th><th>Status</th></tr></thead>
        <tbody>
        <?php if ($students === []): ?><tr><td colspan="4"><div class="empty-state"><?= icon('users') ?><p>No students are registered on this class.</p></div></td></tr><?php endif; ?>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><code><?= e($s['admission_number']) ?></code></td>
                <td><?= e($s['last_name'] . ', ' . $s['first_name']) ?></td>
                <td><input class="form-control form-control-sm" type="number" step="0.25" min="0" max="<?= e((string) $a['max_score']) ?>"
                           name="scores[<?= (int) $s['student_id'] ?>]" value="<?= $s['score'] !== null ? e($fmt($s['score'])) : '' ?>"></td>
                <td><?= $s['score_status'] ? status_badge($s['score_status']) : '<span class="text-muted-sm">Not entered</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php if ($students !== []): ?>
        <div class="card-footer d-flex align-items-center gap-3"><button class="btn btn-primary">Save scores</button>
            <span class="text-muted-sm">Leave a box empty for a student with no score yet.</span></div>
    <?php endif; ?>
</form>
<?php endsection(); ?>
