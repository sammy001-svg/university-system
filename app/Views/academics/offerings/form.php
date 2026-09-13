<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1><?= e($isNew ? 'New Class Offering' : 'Edit Class Offering') ?></h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/academics/offerings') ?>">&larr; Back to Offerings</a>
</div>

<div class="card">
    <form method="post" action="<?= $isNew ? url('/academics/offerings') : url('/academics/offerings/' . ($record['id'] ?? '')) ?>">
        <?= csrf_field() ?>
        <?php if (!$isNew): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
        <div class="card-body">
            <div class="row g-3">
                <?php if ($isNew): ?>
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select" required>
                        <?= options(array_column($courses, 'label', 'id'), old('course_id', $record['course_id'] ?? ''), 'Select course') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select name="semester_id" class="form-select" required>
                        <?= options(array_column($semesters, 'name', 'id'), old('semester_id', $record['semester_id'] ?? ''), 'Select semester') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Section <span class="text-danger">*</span></label>
                    <input type="text" name="section" class="form-control" value="<?= e(old('section', $record['section'] ?? '1')) ?>" required>
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">Capacity <span class="text-danger">*</span></label>
                    <input type="number" name="capacity" class="form-control" value="<?= e(old('capacity', $record['capacity'] ?? 50)) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Assigned Lecturer</label>
                    <select name="lecturer_id" class="form-select">
                        <?= options($staff, old('lecturer_id', $record['lecturer_id'] ?? ''), '— Unassigned —') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Delivery Mode <span class="text-danger">*</span></label>
                    <select name="delivery_mode" class="form-select" required>
                        <?= enum_options(['physical', 'online', 'hybrid'], old('delivery_mode', $record['delivery_mode'] ?? 'physical')) ?>
                    </select>
                </div>
                <?php if (!$isNew): ?>
                <div class="col-md-4">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <?= enum_options(['open', 'closed', 'cancelled', 'completed'], old('status', $record['status'] ?? 'open')) ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Offering' : 'Save Changes' ?></button>
            <a href="<?= url('/academics/offerings') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
