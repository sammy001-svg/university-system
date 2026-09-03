<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Apply for Leave</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/hr/leave') ?>">&larr; Back to Leave Requests</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/hr/leave') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                    <select name="leave_type_id" class="form-select" required>
                        <?= options(array_column($leaveTypes, 'name', 'id'), old('leave_type_id'), 'Select Leave Type') ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Reason / Notes</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Provide leave reason..."></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Submit Application</button>
            <a href="<?= url('/hr/leave') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
