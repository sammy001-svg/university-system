<?php
/** The academic office setting an exam's question paper. */
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');
$phaseLabel = ['draft' => 'Draft', 'upcoming' => 'Published - not open yet', 'open' => 'Open now', 'closed' => 'Closed'][$phase];
?>
<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/academics/online-exams/' . $exam['id']) ?>">&larr; <?= e($exam['title']) ?></a></nav>
        <h1>Question paper</h1>
        <p class="lede"><?= e($offering['code'] . ' - ' . $offering['title']) ?> &middot; <?= e(ExamService::TYPES[$exam['exam_type']]) ?>
            &middot; <?= status_badge(['draft' => 'draft', 'upcoming' => 'planned', 'open' => 'open', 'closed' => 'closed'][$phase]) ?> <?= e($phaseLabel) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-light" href="<?= url('/academics/online-exams/' . $exam['id']) ?>"><?= icon('chart', 'ico-sm') ?> Oversight</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/academics/online-exams/' . $exam['id'] . '/edit') ?>"><?= icon('settings', 'ico-sm') ?> Settings</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Questions', (int) $exam['question_count']],
        ['Total marks', rtrim(rtrim(number_format((float) $exam['total_marks'], 2), '0'), '.')],
        ['Time allowed', $exam['duration_minutes'] . ' min'],
        ['Window', date('j M H:i', strtotime($exam['opens_at'])) . ' &ndash; ' . date('j M H:i', strtotime($exam['closes_at']))],
    ] as [$label, $value]): ?>
        <div class="col-6 col-lg-3"><div class="mini-stat"><b><?= $value ?></b><span><?= e($label) ?></span></div></div>
    <?php endforeach; ?>
</div>

<?php if ($phase === 'draft' && $problems !== []): ?>
    <div class="alert alert-info">Before publishing: <?= e(implode(' ', $problems)) ?></div>
<?php endif; ?>
<?php if ($locked): ?>
    <div class="alert alert-warning">Students have started this exam, so the questions are locked to keep every paper the same.</div>
<?php else: ?>
    <div class="alert alert-light border d-flex align-items-start gap-2"><?= icon('shield') ?>
        <div>Question wording is kept off the oversight page so a paper cannot be read by everyone who may view online learning.
            Setting questions here is recorded in the audit trail.</div></div>
<?php endif; ?>

<?php partial('partials.exam-questions', [
    'exam'      => $exam,
    'questions' => $questions,
    'locked'    => $locked,
    'editing'   => $editing,
    'paths'     => [
        'save'       => '/academics/online-exams/' . $exam['id'] . '/questions',
        'self'       => '/academics/online-exams/' . $exam['id'] . '/questions',
        'question'   => '/academics/exam-questions/',
        'deleteExam' => null,
    ],
]) ?>
<?php endsection(); ?>
