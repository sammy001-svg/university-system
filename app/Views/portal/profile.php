<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>My Student Profile</h1>
        <p class="lede"><?= e($student['admission_number']) ?> &middot; <?= e($student['program_name']) ?></p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4 text-center">
            <span class="avatar avatar-xl mx-auto mb-3" style="background:<?= e(avatar_color($student['first_name'].' '.$student['last_name'])) ?>">
                <?= e(initials($student['first_name'], $student['last_name'])) ?>
            </span>
            <h5 class="mb-1"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h5>
            <p class="text-muted mb-2"><code><?= e($student['admission_number']) ?></code></p>
            <?= status_badge($student['status']) ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('user') ?> Student Personal Details</div>
            <form method="post" action="<?= url('/portal/profile') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= e(old('phone', $student['phone'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Physical Address</label>
                            <input type="text" name="physical_address" class="form-control" value="<?= e(old('physical_address', $student['physical_address'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control" value="<?= e(old('emergency_contact_name', $student['emergency_contact_name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" class="form-control" value="<?= e(old('emergency_contact_phone', $student['emergency_contact_phone'] ?? '')) ?>">
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save Profile Updates</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endsection(); ?>
