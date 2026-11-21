<?php
use App\Services\Elearning\ExamService;
use App\Services\Elearning\Oversight;

layout('layouts.app');
section('content');

$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$passRate = Oversight::pct($stats['passed'], $stats['graded']);
$maxBand = max(1, ...array_values($bands));
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= url('/academics/online-exams') ?>">Online Examinations</a> / <?= e($offering['code']) ?></div>
        <h1><?= e($exam['title']) ?></h1>
        <p class="lede">
            <code><?= e($offering['code']) ?></code> Sec <?= e($offering['section']) ?> &middot; <?= e($offering['title']) ?>
            &middot; <?= e(ExamService::TYPES[$exam['exam_type']] ?? $exam['exam_type']) ?>
            &middot; <?= status_badge($badges[$phase] ?? $phase) ?> <?= e($phases[$phase] ?? $phase) ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-light" href="<?= url('/academics/online-exams') ?>">&larr; All Online Exams</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/academics/online-learning/' . $offering['id']) ?>"><?= icon('video', 'ico-sm') ?> Class Activity</a>
        <a class="btn btn-sm btn-outline-secondary btn-icon" href="<?= e(url('/academics/online-exams/' . $exam['id'] . query_with(['export' => 'csv']))) ?>"><?= icon('download', 'ico-sm') ?> Export CSV</a>
        <?php if ($canTeach): ?>
            <a class="btn btn-sm btn-primary" href="<?= url('/teaching/online-exams/' . $exam['id'] . ($phase === 'draft' ? '' : '/results')) ?>"><?= icon('edit', 'ico-sm') ?> Lecturer View</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= $stats['sat'] ?> / <?= $stats['registered'] ?></b>
            <span>Sat<?= $stats['in_progress'] > 0 ? ' (' . $stats['in_progress'] . ' writing)' : '' ?></span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b class="<?= $stats['to_mark'] > 0 ? 'text-warning' : '' ?>"><?= $stats['to_mark'] ?></b>
            <span>To mark<?= $stats['graded'] > 0 ? ' (' . $stats['graded'] . ' marked)' : '' ?></span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= $stats['average'] === null ? '&ndash;' : $stats['average'] . '%' ?></b>
            <span>Average<?= $stats['median'] !== null ? ' &middot; median ' . $stats['median'] . '%' : '' ?></span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= $stats['highest'] === null ? '&ndash;' : $stats['highest'] . '% / ' . $stats['lowest'] . '%' ?></b>
            <span>Highest / lowest</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= $passRate === null ? '&ndash;' : $passRate . '%' ?></b>
            <span>Passed (&ge; <?= (int) $exam['pass_percent'] ?>%)</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b class="<?= $stats['flagged'] > 0 ? 'text-danger' : '' ?>"><?= $stats['flagged'] ?></b>
            <span>Integrity flags<?= $stats['timed_out'] > 0 ? ' &middot; ' . $stats['timed_out'] . ' timed out' : '' ?></span>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <?= icon('users') ?> Student Attempt Register
                <span class="badge text-bg-light ms-1"><?= count($rows) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Started / Submitted</th>
                        <th class="text-end">Score</th>
                        <th class="text-end">%</th>
                        <th>Integrity</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="6"><div class="empty-state"><?= icon('users') ?><p class="mb-0">No students are registered on this class offering.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $pct = $r['score'] !== null && (float) $r['max_score'] > 0 ? round((float) $r['score'] / (float) $r['max_score'] * 100, 1) : null;
                    ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($r['last_name'] . ', ' . $r['first_name']) ?></div>
                                <div class="text-muted-sm"><code><?= e($r['admission_number']) ?></code></div>
                            </td>
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
                                <?php if ((int) ($r['auto_submitted'] ?? 0) === 1): ?>
                                    <span class="badge text-bg-light">Timed out</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted-sm text-nowrap">
                                <?php if ($r['started_at']): ?>
                                    <div>In: <?= fdatetime($r['started_at']) ?></div>
                                    <?php if ($r['submitted_at']): ?><div>Out: <?= fdatetime($r['submitted_at']) ?></div><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold">
                                <?= $r['score'] !== null && $r['status'] !== 'in_progress' ? $fmt($r['score']) . ' / ' . $fmt($r['max_score']) : '&ndash;' ?>
                            </td>
                            <td class="text-end fw-semibold <?= $pct !== null && $pct < (float) $exam['pass_percent'] ? 'text-danger' : '' ?>">
                                <?= $pct !== null && $r['status'] === 'graded' ? $pct . '%' : '&ndash;' ?>
                            </td>
                            <td>
                                <?php if ((int) ($r['focus_lost'] ?? 0) > 0): ?>
                                    <span class="badge text-bg-warning">Left window &times;<?= (int) $r['focus_lost'] ?></span>
                                <?php elseif ($r['attempt_id'] !== null): ?>
                                    <span class="text-muted-sm">&ndash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><?= icon('chart') ?> Score Distribution</div>
            <div class="card-body">
                <?php if ($stats['graded'] === 0): ?>
                    <p class="text-muted-sm mb-0">No marked attempts yet.</p>
                <?php else: ?>
                    <?php foreach ($bands as $label => $count): ?>
                        <div class="hbar">
                            <div class="hbar-label"><?= e($label) ?></div>
                            <div class="hbar-track"><span style="width:<?= round($count / $maxBand * 100, 1) ?>%"></span></div>
                            <div class="hbar-value"><?= $count ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><?= icon('clipboard') ?> Paper Composition</div>
            <div class="card-body">
                <?php if ($composition === []): ?>
                    <p class="text-muted-sm mb-0">No questions added yet.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Question type</th><th class="text-end">Count</th><th class="text-end">Marks</th></tr></thead>
                        <tbody>
                        <?php foreach ($composition as $c): ?>
                            <tr>
                                <td><?= e(ExamService::QUESTION_TYPES[$c['question_type']] ?? $c['question_type']) ?></td>
                                <td class="text-end"><?= (int) $c['questions'] ?></td>
                                <td class="text-end fw-semibold"><?= $fmt($c['marks']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-end"><?= (int) $exam['question_count'] ?></th>
                                <th class="text-end"><?= $fmt($exam['total_marks']) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
                <p class="text-muted-sm mt-2 mb-0">Question wording is visible only to the teaching team to keep the paper secure.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><?= icon('settings') ?> Exam Settings &amp; Gradebook</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Lecturer</dt><dd class="col-7"><?= $offering['lecturer_name'] !== '' ? e($offering['lecturer_name']) : 'Unassigned' ?></dd>
                    <dt class="col-5 text-muted">Window</dt><dd class="col-7"><?= fdatetime($exam['opens_at']) ?><br><?= fdatetime($exam['closes_at']) ?></dd>
                    <dt class="col-5 text-muted">Duration</dt><dd class="col-7"><?= (int) $exam['duration_minutes'] ?> minutes</dd>
                    <dt class="col-5 text-muted">Pass mark</dt><dd class="col-7"><?= (int) $exam['pass_percent'] ?>%</dd>
                    <dt class="col-5 text-muted">Randomisation</dt><dd class="col-7"><?= (int) $exam['shuffle_questions'] === 1 ? 'Questions shuffled' : 'Fixed order' ?><?= (int) $exam['shuffle_options'] === 1 ? ', options shuffled' : '' ?></dd>
                    <dt class="col-5 text-muted">Result release</dt><dd class="col-7"><?= e(ExamService::RESULT_POLICIES[$exam['show_results']] ?? $exam['show_results']) ?><?= $exam['show_results'] === 'manual' ? ((int) $exam['results_released'] === 1 ? ' (Released)' : ' (Withheld)') : '' ?></dd>
                    <dt class="col-5 text-muted mb-0">Gradebook</dt>
                    <dd class="col-7 mb-0">
                        <?php if ($assessment): ?>
                            Transferred to <strong><?= e($assessment['title']) ?></strong> (<?= $fmt($assessment['weight']) ?>%)
                        <?php else: ?>
                            Not transferred yet
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>

