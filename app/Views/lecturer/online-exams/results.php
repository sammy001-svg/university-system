<?php
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');
$phase = ExamService::phase($exam);
$fmt   = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
?>
<div class="page-head">
    <div>
        <h1>Results &middot; <?= e($exam['title']) ?></h1>
        <p class="lede"><?= e($offering['code'] . ' - ' . $offering['title']) ?> &middot; out of <?= $fmt($exam['total_marks']) ?> marks
            &middot; pass mark <?= (int) $exam['pass_percent'] ?>%</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-light" href="<?= url('/teaching/online-exams/' . $exam['id']) ?>">&larr; Exam paper</a>
        <a class="btn btn-sm btn-outline-secondary btn-icon" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download', 'ico-sm') ?> Export CSV</a>
        <?php if ($exam['show_results'] === 'manual'): ?>
            <form method="post" action="<?= url('/teaching/online-exams/' . $exam['id'] . '/status') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= (int) $exam['results_released'] === 1 ? 'withhold' : 'release' ?>">
                <button class="btn btn-sm <?= (int) $exam['results_released'] === 1 ? 'btn-outline-secondary' : 'btn-primary' ?>">
                    <?= (int) $exam['results_released'] === 1 ? 'Hide results from students' : 'Release results to students' ?></button>
            </form>
        <?php endif; ?>
        <?php if ($phase === 'open'): ?>
            <form method="post" action="<?= url('/teaching/online-exams/' . $exam['id'] . '/status') ?>" data-confirm="Close the exam now? Students still writing will be submitted.">
                <?= csrf_field() ?><input type="hidden" name="action" value="close">
                <button class="btn btn-sm btn-outline-danger">Close exam now</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Sat the exam', $stats['sat'] . ' / ' . $stats['registered']],
        ['Writing now', $stats['in_progress']],
        ['To mark', $stats['to_mark']],
        ['Average', $stats['average'] === null ? '&ndash;' : $stats['average'] . '%'],
        ['Highest / lowest', $stats['highest'] === null ? '&ndash;' : $stats['highest'] . '% / ' . $stats['lowest'] . '%'],
        ['Passed', $stats['graded'] > 0 ? $stats['passed'] . ' of ' . $stats['graded'] : '&ndash;'],
    ] as [$label, $value]): ?>
        <div class="col-6 col-md-4 col-xl-2"><div class="mini-stat"><b><?= $value ?></b><span><?= e($label) ?></span></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card">
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Student</th><th>Status</th><th>Submitted</th><th class="text-end">Score</th><th class="text-end">%</th><th>Integrity</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $pct = $r['score'] !== null && (float) $r['max_score'] > 0 ? round((float) $r['score'] / (float) $r['max_score'] * 100, 1) : null; ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e($r['last_name'] . ', ' . $r['first_name']) ?></div>
                                <div class="text-muted-sm"><?= e($r['admission_number']) ?></div></td>
                            <td>
                                <?php if ($r['attempt_id'] === null): ?>
                                    <span class="badge text-bg-light"><?= $phase === 'closed' ? 'Did not sit' : 'Not started' ?></span>
                                <?php elseif ($r['status'] === 'in_progress'): ?>
                                    <span class="badge text-bg-info">Writing</span>
                                <?php elseif ($r['status'] === 'submitted'): ?>
                                    <span class="badge text-bg-warning">To mark</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Marked</span>
                                <?php endif; ?>
                                <?php if ((int) $r['auto_submitted'] === 1): ?><span class="badge text-bg-light" title="Submitted automatically when time ran out">Timed out</span><?php endif; ?>
                            </td>
                            <td class="text-muted-sm"><?= $r['submitted_at'] ? fdatetime($r['submitted_at']) : '' ?></td>
                            <td class="text-end fw-semibold"><?= $r['score'] !== null && $r['status'] !== 'in_progress' ? $fmt($r['score']) : '' ?></td>
                            <td class="text-end <?= $pct !== null && $pct < (float) $exam['pass_percent'] ? 'text-danger' : '' ?>"><?= $pct !== null && $r['status'] === 'graded' ? $pct : '' ?></td>
                            <td><?php if ((int) $r['focus_lost'] > 0): ?>
                                    <span class="badge text-bg-warning" title="Times the student left the exam window">Left window &times;<?= (int) $r['focus_lost'] ?></span>
                                <?php elseif ($r['attempt_id'] !== null): ?><span class="text-muted-sm">&ndash;</span><?php endif; ?></td>
                            <td class="text-end">
                                <?php if ($r['attempt_id'] !== null && $r['status'] !== 'in_progress'): ?>
                                    <a class="btn btn-sm <?= $r['status'] === 'submitted' ? 'btn-primary' : 'btn-light' ?>" href="<?= url('/teaching/online-exams/attempts/' . $r['attempt_id']) ?>">
                                        <?= $r['status'] === 'submitted' ? 'Mark' : 'View' ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="7"><div class="empty-state"><?= icon('users') ?><p>No students are registered on this class.</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card">
            <div class="card-header"><?= icon('chart') ?> Gradebook</div>
            <div class="card-body">
                <?php if ($assessment): ?>
                    <p class="small mb-2">Linked to the assessment <strong><?= e($assessment['title']) ?></strong>
                        (weight <?= $fmt($assessment['weight']) ?>%). Transfer again after marking more papers.</p>
                <?php else: ?>
                    <p class="small mb-2">Copy these marks into the course gradebook as an assessment, so they count towards the final result.</p>
                <?php endif; ?>
                <form method="post" action="<?= url('/teaching/online-exams/' . $exam['id'] . '/transfer') ?>">
                    <?= csrf_field() ?>
                    <label class="form-label small">Weight in the final grade (%)</label>
                    <input class="form-control form-control-sm mb-2" type="number" name="weight" min="0" max="100" step="0.5"
                           value="<?= e((string) ($assessment['weight'] ?? ($exam['exam_type'] === 'final' ? 70 : ($exam['exam_type'] === 'midterm' ? 20 : 10)))) ?>">
                    <button class="btn btn-sm btn-primary w-100" <?= $stats['graded'] === 0 ? 'disabled' : '' ?>>Transfer marked scores</button>
                </form>
                <?php if ($stats['to_mark'] > 0): ?>
                    <p class="text-muted-sm mt-2 mb-0"><?= $stats['to_mark'] ?> paper(s) have written answers to mark first.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
