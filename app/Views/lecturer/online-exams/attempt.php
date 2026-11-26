<?php
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
?>
<div class="page-head">
    <div>
        <h1><?= e($attempt['first_name'] . ' ' . $attempt['last_name']) ?></h1>
        <p class="lede"><?= e($attempt['admission_number']) ?> &middot; <?= e($exam['title']) ?> &middot;
            started <?= fdatetime($attempt['started_at']) ?><?= $attempt['submitted_at'] ? ', submitted ' . fdatetime($attempt['submitted_at']) : '' ?>
            <?= (int) $attempt['auto_submitted'] === 1 ? '(time ran out)' : '' ?>
            <?= (int) $attempt['focus_lost'] > 0 ? '&middot; left the exam window ' . (int) $attempt['focus_lost'] . ' time(s)' : '' ?></p>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="text-end"><div class="figure-lg"><?= $fmt($attempt['score']) ?><small class="fs-6 text-muted"> / <?= $fmt($attempt['max_score']) ?></small></div>
            <div class="text-muted-sm"><?= $attempt['status'] === 'graded' ? 'Marked' : 'Marking incomplete' ?></div></div>
        <a class="btn btn-sm btn-light" href="<?= url('/teaching/online-exams/' . $exam['id'] . '/results') ?>">&larr; Results</a>
    </div>
</div>

<?php partial('partials.exam-marking', [
    'exam'      => $exam,
    'attempt'   => $attempt,
    'questions' => $questions,
    'answers'   => $answers,
    'paths'     => ['save' => '/teaching/online-exams/attempts/' . $attempt['id']],
]) ?>
<?php endsection(); ?>
