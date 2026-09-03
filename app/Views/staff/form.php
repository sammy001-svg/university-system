<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1><?= e($isNew ? 'Add Staff Member' : 'Edit Staff') ?></h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/staff') ?>">&larr; Back to Staff</a>
</div>

<div class="card">
    <form method="post" action="<?= $isNew ? url('/staff') : url('/staff/' . ($record['id'] ?? '')) ?>">
        <?= csrf_field() ?>
        <?php if (!$isNew): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
        <div class="card-body">
            <?php if ($isNew): ?>
            <h6 class="text-muted mb-3">Account Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" value="<?= e(old('first_name', $record['first_name'] ?? '')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" value="<?= e(old('last_name', $record['last_name'] ?? '')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= e(old('email', $record['email'] ?? '')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= e(old('phone', $record['phone'] ?? '')) ?>">
                </div>
            </div>
            <hr class="my-3">
            <?php endif; ?>

            <h6 class="text-muted mb-3">Staff Details</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" class="form-control" value="<?= e(old('designation', $record['designation'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <?= options(array_column($departments, 'name', 'id'), old('department_id', $record['department_id'] ?? ''), '— None —') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Staff Category <span class="text-danger">*</span></label>
                    <select name="staff_category" class="form-select" required>
                        <?= enum_options(['academic','administrative','support','technical'], old('staff_category', $record['staff_category'] ?? '')) ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                    <select name="employment_type" class="form-select" required>
                        <?= enum_options(['permanent','contract','part_time','visiting','intern'], old('employment_type', $record['employment_type'] ?? '')) ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date Joined</label>
                    <input type="date" name="date_joined" class="form-control" value="<?= e(old('date_joined', substr($record['date_joined'] ?? '', 0, 10))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Basic Salary</label>
                    <input type="number" step="0.01" name="basic_salary" class="form-control" value="<?= e(old('basic_salary', $record['basic_salary'] ?? '')) ?>">
                </div>
                <?php if (!$isNew): ?>
                <div class="col-md-4">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <?= enum_options(['active','on_leave','suspended','terminated','retired'], old('status', $record['status'] ?? 'active')) ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $isNew ? 'Add Staff Member' : 'Save Changes' ?></button>
            <a href="<?= url('/staff') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
