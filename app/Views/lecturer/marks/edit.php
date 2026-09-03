<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Mark Entry – <?= e($offering['code']) ?></h1>
        <p class="lede"><?= e($offering['title']) ?> &middot; Section <?= e($offering['section']) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $offering['id']) ?>">&larr; Class</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/teaching/' . $offering['id'] . '/marks') ?>">
        <?= csrf_field() ?>
        <div class="card-header d-flex align-items-center gap-3">
            <?= icon('edit') ?> Marks Entry
            <span class="ms-auto text-muted-sm">Coursework weight: <?= (int)($offering['coursework_weight'] ?? 30) ?>% &nbsp;|&nbsp; Exam weight: <?= (int)($offering['exam_weight'] ?? 70) ?>%</span>
        </div>
        <div class="table-wrap">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>Admission No.</th><th>Name</th>
                    <th class="text-center">Coursework (/100)</th>
                    <th class="text-center">Exam (/100)</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="5"><div class="empty-state"><?= icon('student') ?><p>No students.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td><code><?= e($s['admission_number']) ?></code></td>
                        <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td class="text-center">
                            <input type="number" step="0.1" min="0" max="100"
                                   name="marks[<?= $s['student_id'] ?>][coursework_score]"
                                   class="form-control form-control-sm text-center"
                                   style="width:90px;margin:auto"
                                   value="<?= e(number_format((float)($s['coursework_score'] ?? 0),1)) ?>"
                                   <?= $s['is_published'] ? 'disabled' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="number" step="0.1" min="0" max="100"
                                   name="marks[<?= $s['student_id'] ?>][exam_score]"
                                   class="form-control form-control-sm text-center"
                                   style="width:90px;margin:auto"
                                   value="<?= e(number_format((float)($s['exam_score'] ?? 0),1)) ?>"
                                   <?= $s['is_published'] ? 'disabled' : '' ?>>
                        </td>
                        <td><?= $s['is_published'] ? '<span class="badge text-bg-success">Published</span>' : '<span class="badge text-bg-warning text-dark">Draft</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Marks</button>
            <?php if (can('results.publish')): ?>
                <button type="button" class="btn btn-outline-success"
                    onclick="if(confirm('Publish all results for this class? This cannot be undone.')){ document.getElementById('publish-form').submit(); }">
                    Publish Results
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (can('results.publish')): ?>
<form id="publish-form" method="post" action="<?= url('/teaching/' . $offering['id'] . '/publish') ?>" class="d-none">
    <?= csrf_field() ?>
</form>
<?php endif; ?>
<?php endsection(); ?>
