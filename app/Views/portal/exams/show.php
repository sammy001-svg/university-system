<?php
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$a   = $attempt;
$pct = $a !== null && (float) $a['max_score'] > 0 && $a['score'] !== null ? round((float) $a['score'] / (float) $a['max_score'] * 100, 1) : null;
?>
<div class="page-head">
    <div>
        <h1><?= e($exam['title']) ?></h1>
        <p class="lede"><?= e($offering['code'] . ' - ' . $offering['title']) ?> &middot; <?= e(ExamService::TYPES[$exam['exam_type']]) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/portal/exams') ?>">&larr; All exams</a>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <?php if ($a === null || $a['status'] === 'in_progress'): ?>
            <!-- ============ Lobby ============ -->
            <div class="card mb-3">
                <div class="card-header"><?= icon('exam') ?> Before you begin</div>
                <div class="card-body">
                    <?php if ($exam['instructions']): ?>
                        <div class="mb-3" style="white-space:pre-wrap"><?= e($exam['instructions']) ?></div>
                    <?php endif; ?>
                    <ul class="small mb-3">
                        <li>You have <strong><?= (int) $exam['duration_minutes'] ?> minutes</strong> from the moment you press Start<?= strtotime($exam['closes_at']) - time() < (int) $exam['duration_minutes'] * 60 ? ', but the exam closes at <strong>' . e(date('H:i', strtotime($exam['closes_at']))) . '</strong>, which leaves less time' : '' ?>.</li>
                        <li>The timer keeps running if you close the page; you can return and continue until your time ends.</li>
                        <li>Your answers are saved as you go. When time runs out, the exam is submitted automatically.</li>
                        <li>You have one attempt. Leaving the exam window is recorded and reported to your lecturer.</li>
                        <li>Use a reliable internet connection and do not refresh repeatedly.</li>
                    </ul>
                    <?php if ($a !== null): ?>
                        <a class="btn btn-primary btn-lg" href="<?= url('/portal/exams/' . $exam['id'] . '/take') ?>"><?= icon('play', 'ico-sm') ?> Resume exam</a>
                        <span class="ms-2 text-muted-sm"><?= gmdate('H:i:s', ExamService::secondsLeft($a)) ?> left</span>
                    <?php elseif ($blocked !== null): ?>
                        <div class="alert alert-<?= $phase === 'upcoming' ? 'info' : 'warning' ?> mb-0"><?= e($blocked) ?></div>
                    <?php else: ?>
                        <form method="post" action="<?= url('/portal/exams/' . $exam['id'] . '/start') ?>"
                              data-confirm="Start the exam now? The timer will begin and cannot be paused.">
                            <?= csrf_field() ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="agree" value="1" id="agree" required>
                                <label class="form-check-label" for="agree">I have read the instructions and will complete this exam on my own, without help.</label>
                            </div>
                            <button class="btn btn-primary btn-lg"><?= icon('play', 'ico-sm') ?> Start exam</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (!$visible): ?>
            <div class="card mb-3"><div class="card-body text-center py-5">
                <div class="text-success mb-2"><?= icon('check', 'ico-lg') ?></div>
                <h2 class="h5">Your exam has been submitted</h2>
                <p class="text-muted mb-0">Submitted <?= fdatetime($a['submitted_at']) ?><?= (int) $a['auto_submitted'] === 1 ? ' (automatically, when time ran out)' : '' ?>.
                    <?= match ($exam['show_results']) {
                        'after_close' => 'Your result will be shown here after the exam closes on ' . e(fdatetime($exam['closes_at'])) . '.',
                        'manual'      => 'Your lecturer will release the results.',
                        default       => 'Your answers are being marked.',
                    } ?></p>
            </div></div>
        <?php else: ?>
            <!-- ============ Result ============ -->
            <div class="card mb-3">
                <div class="card-body d-flex flex-wrap align-items-center gap-4">
                    <?php if ($a['status'] === 'graded'): ?>
                        <div class="result-ring" style="--pct: <?= (float) $pct ?>; --ring-color: <?= $pct >= (float) $exam['pass_percent'] ? '#15803d' : '#b91c1c' ?>"><div><?= $pct ?>%</div></div>
                        <div>
                            <div class="figure-lg"><?= $fmt($a['score']) ?> <small class="fs-5 text-muted">/ <?= $fmt($a['max_score']) ?></small></div>
                            <div class="mt-1"><?= $pct >= (float) $exam['pass_percent'] ? '<span class="badge text-bg-success">Pass</span>' : '<span class="badge text-bg-danger">Below the pass mark</span>' ?>
                                <span class="text-muted-sm ms-1">pass mark <?= (int) $exam['pass_percent'] ?>%</span></div>
                        </div>
                    <?php else: ?>
                        <div><h2 class="h5 mb-1">Marking in progress</h2>
                            <p class="text-muted mb-0">Your lecturer is marking the written answers. Marks so far: <?= $fmt($a['score']) ?> / <?= $fmt($a['max_score']) ?>.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php foreach ($questions as $i => $q):
                $ans    = $answers[$q['id']] ?? null;
                $chosen = $ans && $ans['option_ids'] ? array_map('intval', explode(',', $ans['option_ids'])) : []; ?>
                <div class="q-card">
                    <div class="d-flex justify-content-between gap-3">
                        <div class="q-body"><span class="q-num">Q<?= $i + 1 ?>.</span><?= e($q['body']) ?></div>
                        <div class="text-nowrap fw-semibold"><?= $ans && $ans['marks_awarded'] !== null ? $fmt($ans['marks_awarded']) : '&ndash;' ?> / <?= $fmt($q['marks']) ?></div>
                    </div>
                    <?php if (in_array($q['question_type'], ['single', 'multiple', 'true_false'], true)): ?>
                        <ul class="q-options">
                            <?php foreach ($q['options'] as $j => $opt): $isChosen = in_array((int) $opt['id'], $chosen, true); ?>
                                <li class="<?= (int) $opt['is_correct'] === 1 ? 'correct' : '' ?> <?= $isChosen ? 'chosen' . ((int) $opt['is_correct'] === 1 ? '' : ' wrong') : '' ?>">
                                    <b><?= chr(65 + $j) ?>.</b> <?= e($opt['body']) ?><?= $isChosen ? ' &larr; your answer' : '' ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="mt-2 p-2 rounded small" style="background:#f8f9fb; white-space:pre-wrap"><?= $ans && trim((string) $ans['answer_text']) !== '' ? e($ans['answer_text']) : '<span class="text-muted">Not answered.</span>' ?></div>
                    <?php endif; ?>
                    <?php if ($ans && $ans['feedback']): ?><div class="small mt-2"><b>Lecturer:</b> <?= e($ans['feedback']) ?></div><?php endif; ?>
                    <?php if ($q['explanation']): ?><div class="text-muted-sm mt-2"><b>Explanation:</b> <?= e($q['explanation']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><?= icon('clipboard') ?> Exam details</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted fw-normal">Status</dt><dd class="col-6"><?= e(['upcoming' => 'Not open yet', 'open' => 'Open', 'closed' => 'Closed'][$phase] ?? $phase) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Opens</dt><dd class="col-6"><?= fdatetime($exam['opens_at']) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Closes</dt><dd class="col-6"><?= fdatetime($exam['closes_at']) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Time allowed</dt><dd class="col-6"><?= (int) $exam['duration_minutes'] ?> minutes</dd>
                    <dt class="col-6 text-muted fw-normal">Questions</dt><dd class="col-6"><?= (int) $exam['question_count'] ?></dd>
                    <dt class="col-6 text-muted fw-normal">Total marks</dt><dd class="col-6"><?= $fmt($exam['total_marks']) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Pass mark</dt><dd class="col-6"><?= (int) $exam['pass_percent'] ?>%</dd>
                    <dt class="col-6 text-muted fw-normal">Attempts</dt><dd class="col-6 mb-0">1</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
