<?php
layout('layouts.app');
section('content');
$todo = count(array_filter($courses, static fn ($c) => !(int) $c['done']));
?>
<div class="page-head"><div><h1>Course Evaluations</h1>
    <p class="lede">Tell us about your courses and lecturers. Your answers are <strong>anonymous</strong>: your name is never stored with them, and lecturers only see combined results.</p></div></div>
<?php if ($courses === []): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><?= icon('chart') ?><p>There is no evaluation open right now.</p></div></div></div>
<?php else: ?>
    <div class="card">
        <div class="card-header"><?= icon('chart') ?> <?= e($courses[0]['period_title']) ?> &middot; closes <?= fdatetime($courses[0]['closes_at']) ?>
            <span class="ms-auto badge text-bg-<?= $todo ? 'warning' : 'success' ?>"><?= $todo ? $todo . ' to do' : 'All done - thank you!' ?></span></div>
        <div class="card-body py-1">
            <?php foreach ($courses as $c): ?>
                <div class="el-item">
                    <div class="el-item-main"><div class="el-item-title"><?= e($c['code']) ?> &middot; <?= e($c['title']) ?></div><div class="el-item-meta"><?= e((string) ($c['lecturer_name'] ?? '')) ?></div></div>
                    <?php if ((int) $c['done']): ?><span class="badge text-bg-success">Done</span>
                    <?php else: ?><a class="btn btn-sm btn-primary" href="<?= url('/portal/evaluations/' . $c['period_id'] . '/' . $c['offering_id']) ?>">Evaluate</a><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php endsection(); ?>
