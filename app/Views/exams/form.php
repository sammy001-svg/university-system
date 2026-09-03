<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1><?= e($isNew ? 'Schedule Examination' : 'Edit Examination') ?></h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/exams') ?>">&larr; Back to Exams</a>
</div>

<div class="card">
    <form method="post" action="<?= $isNew ? url('/exams') : url('/exams/' . ($record['id'] ?? '')) ?>">
        <?= csrf_field() ?>
        <?php if (!$isNew): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
        <div class="card-body">
            <div class="row g-3">
                <?php if ($isNew): ?>
                <div class="col-md-6">
                    <label class="form-label">Course Offering <span class="text-danger">*</span></label>
                    <select name="offering_id" class="form-select" required>
                        <?= options(array_column($offerings, 'title', 'id'), old('offering_id', $record['offering_id'] ?? ''), 'Select Course') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select name="semester_id" class="form-select" required>
                        <?= options(array_column($semesters, 'name', 'id'), old('semester_id', $record['semester_id'] ?? ''), 'Select Semester') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Exam Type <span class="text-danger">*</span></label>
                    <select name="exam_type" class="form-select" required>
                        <?= enum_options(['main','supplementary','special','retake'], old('exam_type', $record['exam_type'] ?? 'main')) ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">Exam Date <span class="text-danger">*</span></label>
                    <input type="date" name="exam_date" class="form-control" value="<?= e(old('exam_date', substr($record['exam_date'] ?? '', 0, 10))) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Start Time <span class="text-danger">*</span></label>
                    <input type="time" name="start_time" class="form-control" value="<?= e(old('start_time', $record['start_time'] ?? '09:00')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Duration (Minutes) <span class="text-danger">*</span></label>
                    <input type="number" name="duration_mins" class="form-control" value="<?= e(old('duration_mins', $record['duration_mins'] ?? 180)) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Exam Room</label>
                    <select name="room_id" class="form-select">
                        <?= options(array_column($rooms, 'label', 'id'), old('room_id', $record['room_id'] ?? ''), '— TBA —') ?>
                    </select>
                </div>
                <?php if ($isNew): ?>
                <div class="col-md-4">
                    <label class="form-label">Max Score <span class="text-danger">*</span></label>
                    <input type="number" name="max_score" class="form-control" value="<?= e(old('max_score', 100)) ?>" required>
                </div>
                <?php else: ?>
                <div class="col-md-4">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <?= enum_options(['scheduled','ongoing','completed','cancelled'], old('status', $record['status'] ?? 'scheduled')) ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $isNew ? 'Schedule Exam' : 'Save Changes' ?></button>
            <a href="<?= url('/exams') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
