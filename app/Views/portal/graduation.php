<?php
use App\Services\Academic\GraduationService;

layout('layouts.app');
section('content');
$a = $audit;
$p = $profile ?? [];
$graduated = $record && $record['status'] === 'graduated';
?>
<div class="page-head"><div><h1><?= $graduated ? 'Alumni' : 'Graduation' ?></h1>
    <p class="lede"><?= $graduated ? 'Congratulations, graduate! Keep in touch with your university.' : 'Apply to graduate and follow your application.' ?></p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/portal/degree-audit') ?>">Degree audit</a></div>

<div class="row g-3">
    <div class="col-xl-7">
        <?php if ($record): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('student') ?> Your graduation <span class="ms-auto"><?= e(GraduationService::STATUSES[$record['status']]) ?></span></div>
                <div class="card-body">
                    <?php if ($graduated): ?>
                        <div class="figure-lg mb-1"><?= e((string) $record['classification']) ?></div>
                        <p class="mb-0">Graduated <?= fdate($record['graduation_date']) ?> with a CGPA of <?= number_format((float) $record['final_cgpa'], 2) ?>.
                            Certificate number <strong><?= e((string) $record['certificate_no']) ?></strong>.</p>
                    <?php else: ?>
                        <?php $steps = ['pending' => 1, 'cleared' => 2, 'approved' => 3, 'graduated' => 4, 'deferred' => 0]; $at = $steps[$record['status']]; ?>
                        <div class="d-flex gap-2 mb-3">
                            <?php foreach (['Applied', 'Cleared', 'Approved', 'Graduated'] as $i => $label): ?>
                                <div class="flex-fill text-center p-2 rounded <?= $at > $i ? 'text-bg-success' : 'bg-light text-muted' ?> small fw-semibold"><?= $label ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($record['status'] === 'deferred'): ?><div class="alert alert-warning mb-2">Your graduation was deferred: <?= e((string) $record['remarks']) ?></div><?php endif; ?>
                        <p class="small mb-0">Name on your certificate: <strong><?= e((string) $record['name_on_certificate']) ?></strong>. If this is wrong, raise a request (change of name) now.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$record || $record['status'] === 'deferred'): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('check') ?> Apply to graduate</div>
                <div class="card-body">
                    <p class="small"><?= $a['percent'] ?>% of your programme is complete: <?= $a['earned'] ?> of <?= $a['required'] ?> credits earned, <?= $a['running'] ?> in progress.
                        <?= $a['eligible'] ? 'You meet every requirement.' : '' ?></p>
                    <?php if ($blocked !== null): ?>
                        <div class="alert alert-info mb-0"><?= e($blocked) ?></div>
                    <?php else: ?>
                        <form method="post" action="<?= url('/portal/graduation') ?>"><?= csrf_field() ?>
                            <label class="form-label">Your full name as it should appear on the certificate</label>
                            <input class="form-control mb-2" name="name_on_certificate" value="<?= e((string) preg_replace('/\s+/', ' ', trim($student['first_name'] . ' ' . ($student['other_name'] ?? '') . ' ' . $student['last_name']))) ?>">
                            <button class="btn btn-primary">Apply to graduate</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($graduated): ?>
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><?= icon('user') ?> My alumni profile</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/portal/alumni-profile') ?>"><?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small">Employer</label><input class="form-control form-control-sm" name="employer" value="<?= e((string) ($p['employer'] ?? '')) ?>"></div>
                            <div class="col-6"><label class="form-label small">Job title</label><input class="form-control form-control-sm" name="job_title" value="<?= e((string) ($p['job_title'] ?? '')) ?>"></div>
                            <div class="col-12"><label class="form-label small">Industry</label><input class="form-control form-control-sm" name="industry" value="<?= e((string) ($p['industry'] ?? '')) ?>"></div>
                            <div class="col-6"><label class="form-label small">City</label><input class="form-control form-control-sm" name="city" value="<?= e((string) ($p['city'] ?? '')) ?>"></div>
                            <div class="col-6"><label class="form-label small">Country</label><input class="form-control form-control-sm" name="country" value="<?= e((string) ($p['country'] ?? 'Liberia')) ?>"></div>
                            <div class="col-6"><label class="form-label small">Personal email</label><input class="form-control form-control-sm" type="email" name="email" value="<?= e((string) ($p['email'] ?? '')) ?>"></div>
                            <div class="col-6"><label class="form-label small">Phone</label><input class="form-control form-control-sm" name="phone" value="<?= e((string) ($p['phone'] ?? '')) ?>"></div>
                            <div class="col-12"><label class="form-label small">LinkedIn</label><input class="form-control form-control-sm" name="linkedin_url" value="<?= e((string) ($p['linkedin_url'] ?? '')) ?>" placeholder="https://linkedin.com/in/..."></div>
                            <div class="col-12"><label class="form-label small">Further study</label><input class="form-control form-control-sm" name="further_study" value="<?= e((string) ($p['further_study'] ?? '')) ?>" placeholder="e.g. MSc, University of Ghana"></div>
                            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="in_directory" value="1" id="dir" <?= (int) ($p['in_directory'] ?? 1) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="dir">The university may list me in its alumni directory and contact me</label></div></div>
                        </div>
                        <button class="btn btn-primary w-100 mt-3">Save</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
