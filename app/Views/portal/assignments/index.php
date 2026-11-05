<?php
layout('layouts.app');
section('content');
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$row = static function (array $a) use ($fmt): string {
    ob_start(); ?>
    <a class="el-item text-decoration-none" href="<?= url('/portal/assignments/' . $a['id']) ?>">
        <div class="el-when <?= $a['submission_id'] === null && $a['phase'] !== 'open' ? 'live' : '' ?>"><b><?= date('j', strtotime($a['due_at'])) ?></b><span><?= date('M', strtotime($a['due_at'])) ?></span></div>
        <div class="el-item-main">
            <div class="el-item-title"><?= e($a['code']) ?> &middot; <?= e($a['title']) ?></div>
            <div class="el-item-meta">Due <?= fdatetime($a['due_at']) ?><?= $a['submission_id'] === null && $a['phase'] === 'open' ? ' &middot; ' . e(time_until($a['due_at'])) . ' left' : '' ?></div>
        </div>
        <?php if ($a['graded_at'] !== null): ?><span class="fw-bold"><?= $fmt($a['score']) ?> / <?= $fmt($a['max_score']) ?></span>
        <?php elseif ($a['submission_id'] !== null): ?><span class="badge text-bg-success">Handed in<?= (int) $a['is_late'] === 1 ? ' (late)' : '' ?></span>
        <?php elseif ($a['phase'] === 'late'): ?><span class="badge text-bg-danger">Overdue</span>
        <?php elseif ($a['phase'] === 'closed'): ?><span class="badge text-bg-secondary">Missed</span>
        <?php else: ?><span class="btn btn-sm btn-primary">Open</span><?php endif; ?>
    </a>
    <?php return (string) ob_get_clean();
};
?>
<div class="page-head">
    <div><h1>Assignments</h1><p class="lede">Coursework set by your lecturers. Hand in before the deadline; late work may lose marks.</p></div>
</div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><?= icon('file') ?> To hand in <span class="badge text-bg-warning"><?= count($groups['todo']) ?></span></div>
            <div class="card-body py-1">
                <?php if ($groups['todo'] === []): ?><div class="empty-state py-4"><?= icon('check') ?><p>Nothing to hand in. Well done!</p></div><?php endif; ?>
                <?php foreach ($groups['todo'] as $a) { echo $row($a); } ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><?= icon('clock') ?> Handed in, awaiting marks</div>
            <div class="card-body py-1">
                <?php if ($groups['submitted'] === []): ?><p class="text-muted-sm py-3 mb-0">None.</p><?php endif; ?>
                <?php foreach ($groups['submitted'] as $a) { echo $row($a); } ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><?= icon('chart') ?> Marked</div>
            <div class="card-body py-1">
                <?php if ($groups['marked'] === []): ?><p class="text-muted-sm py-3 mb-0">None yet.</p><?php endif; ?>
                <?php foreach ($groups['marked'] as $a) { echo $row($a); } ?>
            </div>
        </div>
        <?php if ($groups['missed'] !== []): ?>
            <div class="card">
                <div class="card-header">Missed</div>
                <div class="card-body py-1"><?php foreach ($groups['missed'] as $a) { echo $row($a); } ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
