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

<form method="post" action="<?= url('/teaching/online-exams/attempts/' . $attempt['id']) ?>">
    <?= csrf_field() ?>
    <?php foreach ($questions as $i => $q):
        $a      = $answers[$q['id']] ?? null;
        $chosen = $a && $a['option_ids'] ? array_map('intval', explode(',', $a['option_ids'])) : [];
        $manual = $q['question_type'] === 'essay' || $q['question_type'] === 'short_answer';
        ?>
        <div class="q-card">
            <div class="d-flex justify-content-between gap-3">
                <div class="q-body flex-grow-1"><span class="q-num">Q<?= $i + 1 ?>.</span><?= e($q['body']) ?></div>
                <div class="text-nowrap text-muted-sm"><?= $fmt($q['marks']) ?> mark<?= (float) $q['marks'] === 1.0 ? '' : 's' ?></div>
            </div>
            <?php if (in_array($q['question_type'], ['single', 'multiple', 'true_false'], true)): ?>
                <ul class="q-options">
                    <?php foreach ($q['options'] as $j => $opt): $isChosen = in_array((int) $opt['id'], $chosen, true); ?>
                        <li class="<?= (int) $opt['is_correct'] === 1 ? 'correct' : '' ?> <?= $isChosen ? 'chosen' . ((int) $opt['is_correct'] === 1 ? '' : ' wrong') : '' ?>">
                            <b><?= chr(65 + $j) ?>.</b> <?= e($opt['body']) ?> <?= $isChosen ? '&larr; chosen' : '' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($chosen === []): ?><div class="text-muted-sm mt-1">Not answered.</div><?php endif; ?>
            <?php else: ?>
                <div class="mt-2 p-3 rounded" style="background:#f8f9fb; white-space:pre-wrap"><?= $a && trim((string) $a['answer_text']) !== '' ? e($a['answer_text']) : '<span class="text-muted">Not answered.</span>' ?></div>
                <?php if ($q['question_type'] === 'short_answer'): ?>
                    <div class="text-muted-sm mt-1">Accepted: <?= e(implode(' / ', array_column($q['options'], 'body'))) ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="row g-2 align-items-end mt-2">
                <div class="col-sm-3 col-lg-2">
                    <label class="form-label small mb-1">Marks awarded</label>
                    <input class="form-control form-control-sm <?= $a !== null && $a['marks_awarded'] === null ? 'is-invalid' : '' ?>" type="number"
                           name="marks[<?= $q['id'] ?>]" min="0" max="<?= e((string) $q['marks']) ?>" step="0.25"
                           value="<?= $a !== null && $a['marks_awarded'] !== null ? e($fmt($a['marks_awarded'])) : '' ?>"
                           <?= $manual ? '' : 'title="Marked automatically; change only to override"' ?>>
                </div>
                <div class="col">
                    <label class="form-label small mb-1">Feedback to the student (optional)</label>
                    <input class="form-control form-control-sm" name="feedback[<?= $q['id'] ?>]" value="<?= e((string) ($a['feedback'] ?? '')) ?>">
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($attempt['status'] !== 'in_progress'): ?>
        <div class="d-flex gap-2 justify-content-end mb-4">
            <button class="btn btn-outline-primary" name="next" value="0">Save marks</button>
            <button class="btn btn-primary" name="next" value="1">Save and mark next paper</button>
        </div>
    <?php else: ?>
        <div class="alert alert-info">The student is still writing. Marking opens once the paper is submitted.</div>
    <?php endif; ?>
</form>
<?php endsection(); ?>
