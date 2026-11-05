<?php
layout('layouts.app');
section('content');
$label = ['pending' => 'Self-assessment to complete', 'self_submitted' => 'With your appraiser', 'reviewed' => 'Reviewed - please acknowledge', 'acknowledged' => 'Complete'];
?>
<div class="page-head"><div><h1>My Appraisal</h1><p class="lede">Your performance appraisals, and any you are appraising.</p></div></div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><?= icon('user') ?> My appraisals</div>
            <div class="card-body py-1">
                <?php if ($appraisals === []): ?><p class="text-muted-sm py-3 mb-0">No appraisal has been opened for you yet.</p><?php endif; ?>
                <?php foreach ($appraisals as $a): ?>
                    <a class="el-item text-decoration-none" href="<?= url('/appraisals/' . $a['id']) ?>">
                        <div class="el-item-main"><div class="el-item-title"><?= e($a['cycle_title']) ?></div>
                            <div class="el-item-meta"><?= e($label[$a['status']]) ?><?= $a['due_date'] && $a['status'] === 'pending' ? ' &middot; due ' . fdate($a['due_date']) : '' ?></div></div>
                        <?= $a['overall_rating'] !== null ? '<b>' . number_format((float) $a['overall_rating'], 2) . ' / 5</b>' : '' ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><?= icon('check') ?> Appraisals I review</div>
            <div class="card-body py-1">
                <?php if ($reviews === []): ?><p class="text-muted-sm py-3 mb-0">You are not appraising anyone in an open cycle.</p><?php endif; ?>
                <?php foreach ($reviews as $a): ?>
                    <a class="el-item text-decoration-none" href="<?= url('/appraisals/' . $a['id']) ?>">
                        <div class="el-item-main"><div class="el-item-title"><?= e($a['staff_name']) ?></div><div class="el-item-meta"><?= e($a['cycle_title']) ?></div></div>
                        <?= $a['status'] === 'self_submitted' ? '<span class="badge text-bg-warning">To review</span>' : status_badge($a['status'] === 'pending' ? 'pending' : 'completed') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
