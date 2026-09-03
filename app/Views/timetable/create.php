<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Add Timetable Slot</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/timetable') ?>">&larr; Back to Timetable</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/timetable') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Course Offering <span class="text-danger">*</span></label>
                    <select name="offering_id" class="form-select" required>
                        <?= options(array_column($offerings, 'title', 'id'), old('offering_id'), 'Select Offering') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select name="semester_id" class="form-select" required>
                        <?= options(array_column($semesters, 'name', 'id'), old('semester_id'), 'Select Semester') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Day of Week <span class="text-danger">*</span></label>
                    <select name="day_of_week" class="form-select" required>
                        <?= enum_options(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'], old('day_of_week', 'monday')) ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Start Time <span class="text-danger">*</span></label>
                    <input type="time" name="start_time" class="form-control" value="08:00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Time <span class="text-danger">*</span></label>
                    <input type="time" name="end_time" class="form-control" value="10:00" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lecture Room</label>
                    <select name="room_id" class="form-select">
                        <?= options(array_column($rooms, 'label', 'id'), old('room_id'), '— TBA —') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Session Type <span class="text-danger">*</span></label>
                    <select name="session_type" class="form-select" required>
                        <?= enum_options(['lecture','tutorial','practical','seminar'], old('session_type', 'lecture')) ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Add Slot</button>
            <a href="<?= url('/timetable') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
