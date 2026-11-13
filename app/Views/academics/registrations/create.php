<?php
layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div>
        <h1>Register a student</h1>
        <p class="lede">Add a student to a class<?= $semester ? ' for ' . e(($semester['academic_year'] ?? '') . ' ' . $semester['name']) : '' ?>.
            The registration is approved straight away and the student's bill is updated.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/academics/registrations') ?>">&larr; Registrations</a>
</div>
<?php if (!$semester): ?>
    <div class="alert alert-warning">No semester is set as current. Set one under Academics &rsaquo; Semesters first.</div>
<?php else: ?>
    <form method="post" action="<?= url('/academics/registrations') ?>" class="card" style="max-width:720px">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Student <span class="req">*</span></label>
                <select class="form-select" name="student_id" required><?= options($students, old_raw('student_id', $studentId ?: ''), 'Choose...') ?></select>
            </div>
            <div class="mb-3">
                <label class="form-label">Class <span class="req">*</span></label>
                <select class="form-select" name="offering_id" required><?= options($offerings, old_raw('offering_id', ''), 'Choose...') ?></select>
                <div class="form-text">Open classes of the current semester, with places taken / capacity.</div>
            </div>
            <?php if ($offerings === []): ?><div class="alert alert-info mb-0">There are no open classes this semester. Create them under Academics &rsaquo; Class Offerings.</div><?php endif; ?>
        </div>
        <div class="card-footer"><button class="btn btn-primary" <?= $offerings === [] ? 'disabled' : '' ?>>Register and update the bill</button></div>
    </form>
<?php endif; ?>
<?php endsection(); ?>
