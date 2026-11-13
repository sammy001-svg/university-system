<?php
layout('layouts.app');
section('content');
$fmt = static fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$weight = array_sum(array_map(static fn ($a) => (float) $a['weight'], $assessments));
$types = ['cat' => 'Test (CAT)', 'assignment' => 'Assignment', 'quiz' => 'Quiz', 'practical' => 'Practical', 'project' => 'Project', 'presentation' => 'Presentation', 'final_exam' => 'Final examination'];
?>
<div class="page-head">
    <div>
        <h1><?= e($offering['code']) ?> &middot; Assessments</h1>
        <p class="lede"><?= e($offering['title']) ?> &middot; the gradebook: each piece of coursework, its marks and its weight in the final grade.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $offering['id']) ?>">&larr; Class list</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/teaching/' . $offering['id'] . '/classroom') ?>">Online classroom</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/teaching/' . $offering['id'] . '/marks') ?>"><?= icon('edit', 'ico-sm') ?> Final marks</a>
    </div>
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><?= icon('chart') ?> Assessments <span class="badge text-bg-light"><?= count($assessments) ?></span>
                <span class="ms-auto small fw-normal">Total weight <b class="<?= $weight > 100 ? 'text-danger' : '' ?>"><?= $fmt($weight) ?>%</b></span></div>
            <div class="table-wrap"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>Assessment</th><th>Type</th><th class="text-end">Out of</th><th class="text-end">Weight</th><th>Due</th><th></th></tr></thead>
                <tbody>
                <?php if ($assessments === []): ?>
                    <tr><td colspan="6"><div class="empty-state"><?= icon('chart') ?><p>No assessments yet. Add one here, or transfer marks from an online exam or assignment.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($assessments as $a): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($a['title']) ?></td>
                        <td class="small"><?= e($types[$a['type']] ?? humanize($a['type'])) ?></td>
                        <td class="text-end"><?= $fmt($a['max_score']) ?></td>
                        <td class="text-end"><?= $fmt($a['weight']) ?>%</td>
                        <td class="text-muted-sm"><?= $a['due_date'] ? date_fmt($a['due_date']) : '' ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-primary" href="<?= url('/teaching/assessments/' . $a['id'] . '/scores') ?>">Scores</a>
                            <?php if (can('results.delete')): ?>
                                <form class="d-inline" method="post" action="<?= url('/teaching/assessments/' . $a['id']) ?>" data-confirm="Delete this assessment and its scores?">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn btn-sm btn-light text-danger"><?= icon('trash', 'ico-sm') ?></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
    <?php if (can('results.create')): ?>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><?= icon('plus') ?> New assessment</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/teaching/' . $offering['id'] . '/assessments') ?>"><?= csrf_field() ?>
                        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="150" placeholder="e.g. Test 1"></div>
                        <div class="mb-2"><label class="form-label">Type</label><select class="form-select" name="type"><?= options($types, 'cat') ?></select></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Marked out of</label><input class="form-control" type="number" step="0.5" min="1" name="max_score" value="100" required></div>
                            <div class="col-6"><label class="form-label">Weight (%)</label><input class="form-control" type="number" step="0.5" min="0" max="100" name="weight" value="10" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Due date</label><input class="form-control" type="date" name="due_date"></div>
                        <button class="btn btn-primary w-100">Add assessment</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
