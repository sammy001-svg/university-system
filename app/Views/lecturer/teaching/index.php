<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>My Teaching</h1>
        <p class="lede">Your assigned courses this semester.</p>
    </div>
</div>

<?php if (empty($offerings)): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><?= icon('teaching') ?><p>No courses assigned to you.</p></div></div></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($offerings as $o): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <code class="text-primary"><?= e($o['code']) ?></code>
                        <?= status_badge($o['status'] ?? 'open') ?>
                    </div>
                    <h6 class="mb-1"><?= e($o['title']) ?></h6>
                    <p class="text-muted-sm mb-3"><?= e($o['semester_name']) ?> &middot; <?= e($o['academic_year']) ?> &middot; Section <?= e($o['section']) ?></p>
                    <div class="d-flex gap-3 text-muted-sm">
                        <span><?= icon('student','ico-sm') ?> <?= (int)($o['enrolled_count'] ?? 0) ?> students</span>
                        <span><?= icon('assessment','ico-sm') ?> <?= (int)($o['assessment_count'] ?? 0) ?> assessments</span>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a class="btn btn-sm btn-primary" href="<?= url('/teaching/' . $o['id']) ?>">View Class</a>
                    <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $o['id'] . '/marks') ?>">Marks</a>
                    <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $o['id'] . '/assessments') ?>">Assessments</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endsection(); ?>
