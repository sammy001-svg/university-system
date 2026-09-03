<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>New Attendance Session</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/attendance') ?>">&larr; Back to Sessions</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/attendance/sessions') ?>">
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
                    <label class="form-label">Session Date <span class="text-danger">*</span></label>
                    <input type="date" name="session_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Session Type <span class="text-danger">*</span></label>
                    <select name="session_type" class="form-select" required>
                        <?= enum_options(['lecture','tutorial','practical','seminar'], old('session_type', 'lecture')) ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Start Time</label>
                    <input type="time" name="start_time" class="form-control" value="09:00">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Time</label>
                    <input type="time" name="end_time" class="form-control" value="11:00">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Lecture Topic / Notes</label>
                    <input type="text" name="topic" class="form-control" placeholder="e.g. Introduction to Data Structures">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Create Session</button>
            <a href="<?= url('/attendance') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
