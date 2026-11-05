<?php
layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div>
        <h1>My Courses</h1>
        <p class="lede"><?= $showAll ? 'Every course you have registered for.' : 'Your courses for ' . e(($semester['academic_year'] ?? '') . ' ' . ($semester['name'] ?? 'this semester')) . '.' ?>
            Open a course for its materials, online classes and exams.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm <?= $showAll ? 'btn-light' : 'btn-primary' ?>" href="<?= url('/portal/courses') ?>">This semester</a>
        <a class="btn btn-sm <?= $showAll ? 'btn-primary' : 'btn-light' ?>" href="<?= url('/portal/courses?semester=all') ?>">All semesters</a>
    </div>
</div>

<?php if ($courses === []): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><?= icon('book') ?>
        <p>You have no registered courses<?= $showAll ? '' : ' this semester' ?>.</p>
        <a class="btn btn-sm btn-primary" href="<?= url('/portal/registration') ?>">Go to course registration</a></div></div></div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($courses as $c): $n = $next[$c['offering_id']] ?? null; ?>
        <div class="col-md-6 col-xl-4">
            <a class="course-card" href="<?= url('/portal/courses/' . $c['offering_id']) ?>">
                <div class="course-card-band"></div>
                <div class="course-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="course-code"><?= e($c['code']) ?> &middot; <?= (int) $c['credit_hours'] ?> CR</span>
                        <?php if ($c['grade'] && (int) $c['is_published'] === 1): ?>
                            <span class="badge text-bg-success">Grade <?= e($c['grade']) ?></span>
                        <?php elseif ($c['approval_status'] !== 'approved'): ?>
                            <?= status_badge($c['approval_status']) ?>
                        <?php endif; ?>
                    </div>
                    <h3><?= e($c['title']) ?></h3>
                    <div class="text-muted-sm"><?= e($c['lecturer_name'] ?: 'Lecturer to be assigned') ?></div>
                    <?php if ($showAll): ?><div class="text-muted-sm"><?= e($c['academic_year'] . ' ' . $c['semester_name']) ?></div><?php endif; ?>
                    <?php if ($n !== null): ?>
                        <div class="course-next">
                            <?php if ($n['phase'] === 'live'): ?><span class="live-badge">Live now</span> <?= e($n['title']) ?>
                            <?php else: ?>Next class: <b><?= date('D j M, H:i', strtotime($n['starts_at'])) ?></b><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="course-card-foot">
                    <span><?= icon('file', 'ico-sm') ?> <?= (int) $c['materials'] ?> materials</span>
                    <span><?= icon('video', 'ico-sm') ?> <?= (int) $c['sessions'] ?> classes</span>
                    <span><?= icon('exam', 'ico-sm') ?> <?= (int) $c['exams'] ?> exams</span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php endsection(); ?>
