<?php
layout('layouts.app');
section('content');
$a   = $assignment;
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$handedIn = count(array_filter($rows, static fn (array $r): bool => $r['submission_id'] !== null));
$marked   = count(array_filter($rows, static fn (array $r): bool => $r['graded_at'] !== null));
$late     = count(array_filter($rows, static fn (array $r): bool => (int) $r['is_late'] === 1));
?>
<div class="page-head">
    <div>
        <h1><?= e($a['title']) ?></h1>
        <p class="lede"><?= e($offering['code'] . ' - ' . $offering['title']) ?> &middot; due <?= fdatetime($a['due_at']) ?>
            &middot; out of <?= $fmt($a['max_score']) ?>
            &middot; <?= ['open' => 'open', 'late' => 'past deadline, late work accepted', 'closed' => 'closed', 'draft' => 'draft'][$phase] ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $offering['id'] . '/classroom#assignments') ?>">&larr; Classroom</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/teaching/assignments/' . $a['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?> Edit</a>
        <a class="btn btn-sm btn-outline-secondary btn-icon" href="<?= e(query_with(['export' => 'csv'])) ?>"><?= icon('download', 'ico-sm') ?> CSV</a>
        <?php if ($handedIn === 0): ?>
            <form method="post" action="<?= url('/teaching/assignments/' . $a['id']) ?>" data-confirm="Delete this assignment?">
                <?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([['Handed in', $handedIn . ' / ' . count($rows)], ['Late', $late], ['Marked', $marked], ['To mark', $handedIn - $marked]] as [$label, $value]): ?>
        <div class="col-6 col-lg-3"><div class="mini-stat"><b><?= $value ?></b><span><?= e($label) ?></span></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <?php foreach ($rows as $r): ?>
            <div class="q-card" id="s<?= (int) $r['submission_id'] ?>">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="fw-semibold"><?= e($r['last_name'] . ', ' . $r['first_name']) ?> <span class="text-muted-sm"><?= e($r['admission_number']) ?></span></div>
                        <div class="text-muted-sm">
                            <?php if ($r['submission_id'] === null): ?>
                                <span class="badge text-bg-light">Not handed in</span>
                            <?php else: ?>
                                Handed in <?= fdatetime($r['submitted_at']) ?>
                                <?= (int) $r['is_late'] === 1 ? '<span class="badge text-bg-warning">Late</span>' : '' ?>
                                <?= (int) $r['versions'] > 1 ? '&middot; version ' . (int) $r['versions'] : '' ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($r['graded_at'] !== null): ?>
                        <div class="text-end"><div class="fw-bold fs-5"><?= $fmt($r['score']) ?> / <?= $fmt($a['max_score']) ?></div>
                            <?php if ((float) $r['raw_score'] !== (float) $r['score']): ?><div class="text-muted-sm"><?= $fmt($r['raw_score']) ?> before the late penalty</div><?php endif; ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($r['submission_id'] !== null): ?>
                    <?php if ($r['file_path']): ?>
                        <div class="material mt-2"><span class="material-ico"><?= icon('file', 'ico-sm') ?></span>
                            <a class="fw-semibold" target="_blank" href="<?= url('/files/submissions/' . $r['submission_id']) ?>"><?= e($r['file_name']) ?></a>
                            <span class="text-muted-sm"><?= number_format((int) $r['file_size'] / 1024) ?> KB</span></div>
                    <?php endif; ?>
                    <?php if ($r['text_answer']): ?>
                        <div class="mt-2 p-3 rounded" style="background:#f8f9fb; white-space:pre-wrap; max-height:320px; overflow:auto"><?= e($r['text_answer']) ?></div>
                    <?php endif; ?>
                    <form class="row g-2 align-items-end mt-2" method="post" action="<?= url('/teaching/assignments/' . $a['id'] . '/grade') ?>">
                        <?= csrf_field() ?><input type="hidden" name="submission_id" value="<?= (int) $r['submission_id'] ?>">
                        <div class="col-sm-3 col-lg-2">
                            <label class="form-label small mb-1">Mark / <?= $fmt($a['max_score']) ?></label>
                            <input class="form-control form-control-sm" type="number" name="score" min="0" max="<?= e((string) $a['max_score']) ?>" step="0.25"
                                   value="<?= $r['raw_score'] !== null ? e($fmt($r['raw_score'])) : '' ?>" required>
                        </div>
                        <div class="col">
                            <label class="form-label small mb-1">Feedback</label>
                            <input class="form-control form-control-sm" name="feedback" value="<?= e((string) $r['feedback']) ?>">
                        </div>
                        <div class="col-auto"><button class="btn btn-sm <?= $r['graded_at'] ? 'btn-outline-primary' : 'btn-primary' ?>"><?= $r['graded_at'] ? 'Update' : 'Save mark' ?></button></div>
                    </form>
                    <?php if ((int) $r['is_late'] === 1 && (int) $a['late_penalty'] > 0): ?>
                        <div class="text-muted-sm mt-1">Late: <?= (int) $a['late_penalty'] ?>% will be taken off the mark you enter.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <div class="card"><div class="card-body"><div class="empty-state"><?= icon('users') ?><p>No students on this class list.</p></div></div></div>
        <?php endif; ?>
    </div>
    <div class="col-xl-3">
        <?php if ($a['instructions'] || $a['attachment_name']): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('file') ?> Brief</div>
                <div class="card-body small">
                    <?php if ($a['instructions']): ?><div style="white-space:pre-wrap"><?= e($a['instructions']) ?></div><?php endif; ?>
                    <?php if ($a['attachment_name']): ?><a class="d-block mt-2" target="_blank" href="<?= url('/files/assignments/' . $a['id']) ?>"><?= icon('download', 'ico-sm') ?> <?= e($a['attachment_name']) ?></a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header"><?= icon('chart') ?> Gradebook</div>
            <div class="card-body">
                <p class="small mb-2"><?= $assessment ? 'Linked to <strong>' . e($assessment['title']) . '</strong>. Transfer again after marking more work.'
                    : 'Copy the marks into the course gradebook so they count towards the final result.' ?></p>
                <form method="post" action="<?= url('/teaching/assignments/' . $a['id'] . '/transfer') ?>">
                    <?= csrf_field() ?>
                    <label class="form-label small">Weight in the final grade (%)</label>
                    <input class="form-control form-control-sm mb-2" type="number" name="weight" min="0" max="100" step="0.5" value="<?= e((string) ($assessment['weight'] ?? 10)) ?>">
                    <?php if ($phase !== 'open'): ?>
                        <div class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="missing_as_zero" value="1" id="mz">
                            <label class="form-check-label" for="mz">Record work not handed in as zero</label></div>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-primary w-100" <?= $marked === 0 ? 'disabled' : '' ?>>Transfer marks</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
