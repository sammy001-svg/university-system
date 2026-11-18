<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Application: <?= e($application['application_number']) ?></h1>
        <p class="lede"><?= e($application['first_name'] . ' ' . $application['last_name']) ?> &middot; <?= e($application['program_name'] ?? '') ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/admissions/applications') ?>">&larr; Back to Applications</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><?= icon('file') ?> Application Details</span>
                <?= status_badge($application['status']) ?>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Full Name</dt><dd class="col-sm-8"><?= e($application['first_name'] . ' ' . $application['last_name']) ?></dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($application['email']) ?></dd>
                    <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= e($application['phone']) ?></dd>
                    <dt class="col-sm-4">Program</dt><dd class="col-sm-8"><?= e($application['program_name'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Intake</dt><dd class="col-sm-8"><?= e($application['intake_name'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Study Mode</dt><dd class="col-sm-8"><?= e(humanize($application['study_mode'] ?? '')) ?></dd>
                    <dt class="col-sm-4">Submitted Date</dt><dd class="col-sm-8"><?= date_fmt($application['submitted_at'] ?? '') ?></dd>
                    <dt class="col-sm-4">Score</dt><dd class="col-sm-8"><?= e($application['score'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Remarks</dt><dd class="col-sm-8"><?= e($application['remarks'] ?? '—') ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($application['status'] === 'accepted' && can('admissions.enrol')): ?>
            <div class="card border-success">
                <div class="card-body">
                    <h6 class="mb-1 text-success">Application Accepted</h6>
                    <p class="text-muted-sm">Give the applicant their student ID to enrol them. Use the university's own numbering;
                        the student signs in with this ID, so type it exactly as it appears on their records.</p>
                    <form method="post" action="<?= url('/admissions/applications/' . $application['id'] . '/enrol') ?>"
                          class="row g-2 align-items-end" style="max-width:560px">
                        <?= csrf_field() ?>
                        <div class="col-sm-7">
                            <label class="form-label" for="admission_number">Student ID <span class="req">*</span></label>
                            <input class="form-control" id="admission_number" name="admission_number" maxlength="40" required
                                   value="<?= old('admission_number') ?>" placeholder="e.g. BBIU2026100162">
                        </div>
                        <div class="col-sm-5">
                            <button class="btn btn-success w-100"><?= icon('student', 'ico-sm') ?> Enrol as Student</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <?php if (can('admissions.review')): ?>
        <div class="card">
            <div class="card-header"><?= icon('edit') ?> Review Application</div>
            <form method="post" action="<?= url('/admissions/applications/' . $application['id'] . '/review') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Review Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <?= enum_options(['under_review','shortlisted','accepted','rejected'], $application['status']) ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Score / Rating</label>
                        <input type="number" step="0.1" name="score" class="form-control" value="<?= e($application['score'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks / Comments</label>
                        <textarea name="remarks" class="form-control" rows="3"><?= e($application['remarks'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">Update Application Status</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endsection(); ?>
