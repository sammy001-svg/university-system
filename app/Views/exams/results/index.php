<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Exam Results Management</h1>
        <p class="lede">Manage course marks, compute GPAs &amp; publish results.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($semesterId && can('results.manage')): ?>
            <form method="post" action="<?= url('/exams/results/compute') ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="semester_id" value="<?= e($semesterId) ?>">
                <button class="btn btn-outline-primary btn-sm"><?= icon('refresh', 'ico-sm') ?> Compute GPA</button>
            </form>
            <form method="post" action="<?= url('/exams/results/publish') ?>" class="d-inline" onsubmit="return confirm('Publish all results for this semester?')">
                <?= csrf_field() ?>
                <input type="hidden" name="semester_id" value="<?= e($semesterId) ?>">
                <button class="btn btn-success btn-sm"><?= icon('check', 'ico-sm') ?> Publish Results</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Semester</label>
            <select name="semester_id" class="form-select form-select-sm" data-auto-submit>
                <?= options(array_column($semesters, 'name', 'id'), $_GET['semester_id'] ?? '', 'Select Semester') ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-primary w-100">Load Results</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header"><?= icon('results') ?> Course Results (<?= count($results) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th class="text-center">Score</th>
                    <th class="text-center">Grade</th>
                    <th>Published</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('results') ?><p>Select a semester to view results.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><code><?= e($r['admission_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                    <td><code><?= e($r['code']) ?></code></td>
                    <td><?= e($r['title']) ?></td>
                    <td class="text-center fw-bold"><?= number_format((float)$r['total_score'], 1) ?></td>
                    <td class="text-center"><span class="badge text-bg-dark"><?= e($r['grade']) ?></span></td>
                    <td><?= $r['is_published'] ? '<span class="badge text-bg-success">Published</span>' : '<span class="badge text-bg-warning text-dark">Draft</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
