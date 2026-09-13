<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Capture New Application</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/admissions/applications') ?>">&larr; Back to Applications</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/admissions/applications') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Intake <span class="text-danger">*</span></label>
                    <select name="intake_id" class="form-select" required>
                        <?= options(array_column($intakes, 'name', 'id'), old('intake_id'), 'Select Intake') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Program <span class="text-danger">*</span></label>
                    <select name="program_id" class="form-select" required>
                        <?= options($programs, old('program_id'), 'Select Program') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" value="<?= e(old('first_name')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" value="<?= e(old('last_name')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" required>
                        <?= enum_options(['male','female','other'], old('gender', 'male')) ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" value="<?= e(old('phone')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= e(old('date_of_birth')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nationality <span class="text-danger">*</span></label>
                    <input type="text" name="nationality" class="form-control" value="<?= e(old('nationality', 'Liberian')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">National ID / Passport <span class="text-danger">*</span></label>
                    <input type="text" name="national_id" class="form-control" value="<?= e(old('national_id')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Study Mode <span class="text-danger">*</span></label>
                    <select name="study_mode" class="form-select" required>
                        <?= enum_options(['full_time','part_time','evening','distance'], old('study_mode', 'full_time')) ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Submit Application</button>
            <a href="<?= url('/admissions/applications') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
