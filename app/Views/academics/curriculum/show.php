<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Curriculum: <?= e($program['name']) ?></h1>
        <p class="lede"><?= e($program['code']) ?> &middot; Program Course Structure</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/programs') ?>">&larr; Back to Programs</a>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('course') ?> Curriculum Courses (<?= count($curriculum) ?>)</div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Title</th>
                            <th class="text-center">Year</th>
                            <th class="text-center">Semester</th>
                            <th class="text-center">Credits</th>
                            <th>Type</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($curriculum)): ?>
                        <tr><td colspan="7"><div class="empty-state"><?= icon('course') ?><p>No courses attached to this curriculum.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($curriculum as $item): ?>
                        <tr>
                            <td><code><?= e($item['code']) ?></code></td>
                            <td class="fw-semibold"><?= e($item['title']) ?></td>
                            <td class="text-center"><?= (int)$item['year_of_study'] ?></td>
                            <td class="text-center"><?= (int)($item['semester_number'] ?? 1) ?></td>
                            <td class="text-center"><?= (int)$item['credit_hours'] ?></td>
                            <td><?= $item['is_elective'] ? '<span class="badge text-bg-info">Elective</span>' : '<span class="badge text-bg-primary">Core</span>' ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= url('/programs/' . $program['id'] . '/curriculum/' . $item['course_id'] . '/delete') ?>" onsubmit="return confirm('Remove course from curriculum?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><?= icon('trash', 'ico-sm') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><?= icon('plus') ?> Add Course to Curriculum</div>
            <form method="post" action="<?= url('/programs/' . $program['id'] . '/curriculum') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Course <span class="text-danger">*</span></label>
                        <select name="course_id" class="form-select" required>
                            <?= options(array_column($available, 'title', 'id'), '', 'Select Course') ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year of Study <span class="text-danger">*</span></label>
                        <select name="year_of_study" class="form-select" required>
                            <?= options([1=>'Year 1', 2=>'Year 2', 3=>'Year 3', 4=>'Year 4', 5=>'Year 5'], 1) ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_elective" value="1" id="is_elective">
                        <label class="form-check-label" for="is_elective">Elective Course</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endsection(); ?>
