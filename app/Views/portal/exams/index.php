<?php
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$row = static function (array $x) use ($fmt): string {
    $a = $x['attempt'];
    ob_start(); ?>
    <a class="el-item text-decoration-none" href="<?= url('/portal/exams/' . $x['id']) ?>">
        <div class="el-item-main">
            <div class="el-item-title"><?= e($x['code']) ?> &middot; <?= e($x['title']) ?></div>
            <div class="el-item-meta"><?= e(ExamService::TYPES[$x['exam_type']]) ?> &middot; <?= (int) $x['question_count'] ?> questions &middot; <?= (int) $x['duration_minutes'] ?> minutes
                &middot; <?= $x['phase'] === 'upcoming' ? 'opens ' . fdatetime($x['opens_at']) : ($x['phase'] === 'open' ? 'closes ' . fdatetime($x['closes_at']) : 'closed ' . fdatetime($x['closes_at'])) ?></div>
        </div>
        <?php if ($a !== null && $x['visible'] && $a['status'] === 'graded'): ?>
            <span class="fw-bold"><?= $fmt($a['score']) ?> / <?= $fmt($a['max_score']) ?></span>
        <?php elseif ($a !== null && $a['status'] !== 'in_progress'): ?>
            <span class="badge text-bg-success">Submitted</span>
        <?php elseif ($a !== null): ?>
            <span class="badge text-bg-warning">Resume</span>
        <?php elseif ($x['phase'] === 'open'): ?>
            <span class="btn btn-sm btn-primary">Start</span>
        <?php elseif ($x['phase'] === 'closed'): ?>
            <span class="badge text-bg-light">Not attempted</span>
        <?php else: ?>
            <span class="text-muted-sm">in <?= e(time_until($x['opens_at'])) ?></span>
        <?php endif; ?>
    </a>
    <?php return (string) ob_get_clean();
};
?>
<div class="page-head">
    <div>
        <h1>Online Examinations</h1>
        <p class="lede">Quizzes, tests and examinations set by your lecturers. Each exam is timed from the moment you start it.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><?= icon('exam') ?> Open now <span class="badge text-bg-success"><?= count($groups['open']) ?></span></div>
            <div class="card-body py-1">
                <?php if ($groups['open'] === []): ?><div class="empty-state py-4"><?= icon('exam') ?><p>No exams are open right now.</p></div><?php endif; ?>
                <?php foreach ($groups['open'] as $x) { echo $row($x); } ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><?= icon('calendar') ?> Coming up <span class="badge text-bg-light"><?= count($groups['upcoming']) ?></span></div>
            <div class="card-body py-1">
                <?php if ($groups['upcoming'] === []): ?><p class="text-muted-sm py-3 mb-0">Nothing scheduled.</p><?php endif; ?>
                <?php foreach ($groups['upcoming'] as $x) { echo $row($x); } ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><?= icon('check') ?> Completed and closed</div>
            <div class="card-body py-1">
                <?php if ($groups['done'] === []): ?><p class="text-muted-sm py-3 mb-0">None yet.</p><?php endif; ?>
                <?php foreach ($groups['done'] as $x) { echo $row($x); } ?>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
