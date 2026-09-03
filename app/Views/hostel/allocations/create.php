<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Allocate Hostel Room</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/hostel/allocations') ?>">&larr; Back to Allocations</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/hostel/allocations') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Available Room <span class="text-danger">*</span></label>
                    <select name="room_id" class="form-select" required>
                        <?= options(array_column($rooms, 'label', 'id'), old('room_id'), 'Select Room') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" required>
                        <?= options(array_column($students, 'label', 'id'), old('student_id'), 'Select Student') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Check-In Date <span class="text-danger">*</span></label>
                    <input type="date" name="check_in_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Allocate Room</button>
            <a href="<?= url('/hostel/allocations') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
