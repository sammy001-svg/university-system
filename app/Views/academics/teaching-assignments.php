<?php
/** Who teaches what: assign lecturers to a semester's classes, with each one's load in view. */
layout('layouts.app');
section('content');
$canEdit = can('offerings.edit');
$qs      = static fn (array $extra = []): string => '?' . http_build_query(array_filter(array_merge($filters, $extra), static fn ($v): bool => $v !== '' && $v !== 0));
?>
<div class="page-head">
    <div>
        <h1>Teaching Assignments</h1>
        <p class="lede">A lecturer's classes, attendance, marks, online lessons and examinations all follow from this.
            A class with nobody against it is a class nobody can run.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= count($offerings) ?></b><span>Classes listed</span></div></div>
    <div class="col-6 col-md-3">
        <div class="mini-stat <?= $unassigned > 0 ? 'text-danger' : '' ?>"><b><?= (int) $unassigned ?></b><span>Unassigned this semester</span></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mini-stat <?= $idle > 0 ? 'text-warning' : '' ?>"><b><?= (int) $idle ?></b><span>Lecturers with no class</span></div>
    </div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= count($lecturers) ?></b><span>Teaching staff</span></div></div>
</div>

<?php if ($unassigned > 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2"><?= icon('bell') ?>
        <div><strong><?= (int) $unassigned ?></strong> class<?= $unassigned === 1 ? '' : 'es' ?> this semester
            <?= $unassigned === 1 ? 'has' : 'have' ?> no lecturer. Until one is set, nobody can mark its attendance,
            enter its marks, run its online classes or set its examinations.
            <a class="fw-semibold" href="<?= e($qs(['show' => 'unassigned'])) ?>">Show just those</a></div>
    </div>
<?php endif; ?>

<form class="filter-bar" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select form-select-sm" data-auto-submit>
                <?= options(array_column($semesters, 'label', 'id'), $filters['semester']) ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">College</label>
            <select name="faculty" class="form-select form-select-sm" data-auto-submit>
                <?= options(array_column($faculties, 'name', 'id'), $filters['faculty'], 'Every college') ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Course</label>
            <input type="search" name="q" class="form-control form-control-sm" value="<?= e((string) $filters['q']) ?>" placeholder="Code or title">
        </div>
        <div class="col-md-2">
            <label class="form-label">Show</label>
            <select name="show" class="form-select form-select-sm" data-auto-submit>
                <?= options(['' => 'All classes', 'unassigned' => 'Unassigned only', 'assigned' => 'Assigned only'], $filters['show']) ?>
            </select>
        </div>
        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Go</button></div>
    </div>
</form>

<form method="post" action="<?= url('/academics/teaching-assignments') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="semester" value="<?= (int) $filters['semester'] ?>">
    <input type="hidden" name="faculty" value="<?= (int) $filters['faculty'] ?>">
    <input type="hidden" name="q" value="<?= e((string) $filters['q']) ?>">
    <input type="hidden" name="show" value="<?= e((string) $filters['show']) ?>">

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2"><?= icon('users') ?> Classes
            <span class="badge text-bg-light"><?= count($offerings) ?></span>
            <?php if ($canEdit && $offerings !== []): ?>
                <button class="btn btn-primary btn-sm ms-auto"><?= icon('check', 'ico-sm') ?> Save assignments</button>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Course</th><th>Section</th><th>College</th>
                    <th class="text-center">Credits</th><th class="text-center">Students</th><th style="width:28%">Lecturer</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($offerings === []): ?>
                    <tr><td colspan="6"><div class="empty-state"><?= icon('clipboard') ?>
                        <p>No class matches. Try another semester or college.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($offerings as $o): ?>
                    <tr class="<?= $o['lecturer_id'] === null ? 'table-warning' : '' ?>">
                        <td class="fw-semibold"><?= e($o['code']) ?>
                            <div class="text-muted-sm"><?= e(str_limit((string) $o['title'], 46)) ?></div></td>
                        <td><?= e((string) $o['section']) ?>
                            <div class="text-muted-sm"><?= e(humanize((string) $o['delivery_mode'])) ?></div></td>
                        <td class="text-muted-sm"><?= e(str_limit((string) ($o['faculty_name'] ?? ''), 26)) ?></td>
                        <td class="text-center"><?= (int) $o['credit_hours'] ?></td>
                        <td class="text-center"><?= (int) $o['enrolled_count'] ?><span class="text-muted-sm">/<?= (int) $o['capacity'] ?></span></td>
                        <td>
                            <?php if ($canEdit): ?>
                                <select name="lecturer[<?= (int) $o['id'] ?>]" class="form-select form-select-sm">
                                    <?= options($lecturers, $o['lecturer_id'], 'Nobody assigned') ?>
                                </select>
                            <?php else: ?>
                                <?= $o['lecturer_id'] !== null
                                    ? e((string) $o['lecturer_name'])
                                    : '<span class="badge text-bg-warning">Nobody assigned</span>' ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canEdit && $offerings !== []): ?>
            <div class="card-footer d-flex">
                <span class="text-muted-sm">Set as many as you like, then save them together.</span>
                <button class="btn btn-primary btn-sm ms-auto"><?= icon('check', 'ico-sm') ?> Save assignments</button>
            </div>
        <?php endif; ?>
    </div>
</form>

<div class="card mt-4">
    <div class="card-header"><?= icon('chart') ?> What each lecturer is carrying this semester</div>
    <div class="table-wrap">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
            <tr><th>Lecturer</th><th>College</th><th class="text-center">Classes</th>
                <th class="text-center">Credit hours</th><th class="text-center">Students</th><th>Account</th></tr>
            </thead>
            <tbody>
            <?php foreach ($workload as $w): ?>
                <tr class="<?= (int) $w['classes'] === 0 ? 'text-muted' : '' ?>">
                    <td class="fw-semibold"><?= e(trim((string) $w['name'])) ?>
                        <div class="text-muted-sm"><code><?= e((string) $w['staff_number']) ?></code></div></td>
                    <td class="text-muted-sm"><?= e(str_limit((string) $w['faculty_name'], 30)) ?></td>
                    <td class="text-center <?= (int) $w['classes'] === 0 ? '' : 'fw-semibold' ?>"><?= (int) $w['classes'] ?></td>
                    <td class="text-center"><?= (int) $w['credits'] ?></td>
                    <td class="text-center"><?= (int) $w['students'] ?></td>
                    <td>
                        <?php if ((int) $w['never_signed_in'] === 1): ?>
                            <span class="badge text-bg-warning">No password issued</span>
                        <?php elseif ((int) $w['classes'] === 0): ?>
                            <span class="text-muted-sm">no class this semester</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">Ready</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted-sm">
        A lecturer whose password has never been issued cannot sign in at all. Issue login slips from
        <a href="<?= url('/admin/data-imports/slips') ?>">Data Imports &rsaquo; Login slips</a>.
    </div>
</div>
<?php endsection(); ?>
