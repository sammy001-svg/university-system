<?php
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');
$phase   = ExamService::phase($exam);
$phaseLabel = ['draft' => 'Draft', 'upcoming' => 'Published - not open yet', 'open' => 'Open now', 'closed' => 'Closed'][$phase];
?>
<div class="page-head">
    <div>
        <h1><?= e($exam['title']) ?></h1>
        <p class="lede"><?= e($offering['code'] . ' - ' . $offering['title']) ?> &middot; <?= e(ExamService::TYPES[$exam['exam_type']]) ?>
            &middot; <?= status_badge(['draft' => 'draft', 'upcoming' => 'planned', 'open' => 'open', 'closed' => 'closed'][$phase]) ?> <?= e($phaseLabel) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $offering['id'] . '/classroom#exams') ?>">&larr; Classroom</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/teaching/online-exams/' . $exam['id'] . '/edit') ?>"><?= icon('settings', 'ico-sm') ?> Settings</a>
        <?php if ($phase !== 'draft'): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= url('/teaching/online-exams/' . $exam['id'] . '/results') ?>"><?= icon('chart', 'ico-sm') ?> Results</a>
        <?php endif; ?>
        <form method="post" action="<?= url('/teaching/online-exams/' . $exam['id'] . '/status') ?>"
              <?= $phase === 'open' ? 'data-confirm="Close the exam now? Students still writing will be submitted."' : '' ?>>
            <?= csrf_field() ?>
            <?php if ($phase === 'draft'): ?>
                <input type="hidden" name="action" value="publish">
                <button class="btn btn-sm btn-primary" <?= $problems !== [] ? 'disabled' : '' ?>><?= icon('check', 'ico-sm') ?> Publish to students</button>
            <?php elseif ($phase !== 'closed'): ?>
                <input type="hidden" name="action" value="<?= $locked ? 'close' : 'unpublish' ?>">
                <button class="btn btn-sm btn-outline-danger"><?= $locked ? 'Close exam now' : 'Unpublish' ?></button>
            <?php endif; ?>
        </form>
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
<?php endif; ?>

<?php partial('partials.exam-questions', [
    'exam'      => $exam,
    'questions' => $questions,
    'locked'    => $locked,
    'editing'   => $editing ?? null,
    'paths'     => [
        'save'       => '/teaching/online-exams/' . $exam['id'] . '/questions',
        'self'       => '/teaching/online-exams/' . $exam['id'],
        'question'   => '/teaching/exam-questions/',
        'deleteExam' => '/teaching/online-exams/' . $exam['id'],
    ],
]) ?>
<?php endsection(); ?>
