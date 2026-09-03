<?php
/**
 * Public online application form.
 * Layout: standalone public page (no sidebar/auth required).
 */
$appName = $appName ?? 'University';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> &middot; <?= e($appName) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <style>
        body { background: var(--bg, #f1f5f9); }
        .apply-wrap { max-width: 860px; margin: 0 auto; padding: 2rem 1rem 4rem; }
        .apply-header { text-align: center; margin-bottom: 2rem; }
        .apply-header .crest {
            display: inline-grid; place-items: center; width: 52px; height: 52px;
            border-radius: 14px; background: var(--accent, #3b82f6);
            color: #fff; font-weight: 700; font-size: 1.1rem; margin-bottom: .75rem;
        }
        .apply-header h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: .3rem; }
        .apply-header p  { color: #64748b; margin-bottom: 0; }
        .section-title { font-size: .7rem; font-weight: 700; text-transform: uppercase;
                         letter-spacing: .08em; color: #94a3b8; margin: 1.75rem 0 .75rem; }
        .form-card { background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 2rem; }
        .nav-apply { display: flex; justify-content: flex-end; gap: 1rem;
                     padding: .75rem 0; margin-bottom: 1.5rem; font-size: .875rem; }
        .nav-apply a { color: var(--accent, #3b82f6); text-decoration: none; }
        .nav-apply a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="apply-wrap">

    <div class="nav-apply">
        <a href="<?= url('/apply/status') ?>">Check application status &rarr;</a>
        <a href="<?= url('/login') ?>">Staff / student login</a>
    </div>

    <div class="apply-header">
        <div class="crest"><?= e(substr((string)($appShortName ?? $appName), 0, 2)) ?></div>
        <h1><?= e($appName) ?> &mdash; Online Application</h1>
        <p>Complete all required fields (*) and submit. You will receive an application number upon submission.</p>
    </div>

    <?php if (empty($intakes)): ?>
        <div class="form-card text-center py-5">
            <div style="font-size:3rem">📋</div>
            <h3 class="mt-3">No open intakes at the moment</h3>
            <p class="text-muted">Admission applications are currently closed. Please check back later or contact the admissions office.</p>
            <a href="<?= url('/login') ?>" class="btn btn-outline-primary mt-2">Go to sign in</a>
        </div>
    <?php else: ?>

    <div class="form-card">
        <?php partial('partials.flash'); ?>

        <form method="post" action="<?= url('/apply') ?>" novalidate>
            <?= csrf_field() ?>

            <!-- INTAKE & PROGRAMME -->
            <div class="section-title">Programme selection</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="intake_id">Intake <span class="req">*</span></label>
                    <select class="form-select <?= has_error('intake_id') ? 'is-invalid' : '' ?>"
                            id="intake_id" name="intake_id" required>
                        <option value="">— select intake —</option>
                        <?php foreach ($intakes as $intake): ?>
                            <option value="<?= e($intake['id']) ?>"
                                <?= old('intake_id') == $intake['id'] ? 'selected' : '' ?>>
                                <?= e($intake['name']) ?>
                                <?php if ($intake['application_close']): ?>
                                    (closes <?= e(date('d M Y', strtotime((string)$intake['application_close']))) ?>)
                                <?php endif ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if (has_error('intake_id')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('intake_id') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="program_id">Programme <span class="req">*</span></label>
                    <select class="form-select <?= has_error('program_id') ? 'is-invalid' : '' ?>"
                            id="program_id" name="program_id" required>
                        <option value="">— select programme —</option>
                        <?php
                        $lastFaculty = null;
                        foreach ($programs as $prog):
                            if ($prog['faculty_name'] !== $lastFaculty):
                                if ($lastFaculty !== null): echo '</optgroup>'; endif;
                                echo '<optgroup label="' . e($prog['faculty_name']) . '">';
                                $lastFaculty = $prog['faculty_name'];
                            endif;
                        ?>
                            <option value="<?= e($prog['id']) ?>"
                                <?= old('program_id') == $prog['id'] ? 'selected' : '' ?>>
                                <?= e($prog['code'] . ' – ' . $prog['name']) ?>
                            </option>
                        <?php endforeach; if ($lastFaculty !== null): echo '</optgroup>'; endif; ?>
                    </select>
                    <?php if (has_error('program_id')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('program_id') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="study_mode">Study mode <span class="req">*</span></label>
                    <select class="form-select <?= has_error('study_mode') ? 'is-invalid' : '' ?>"
                            id="study_mode" name="study_mode" required>
                        <option value="">— select —</option>
                        <?php foreach (['full_time' => 'Full-time', 'part_time' => 'Part-time', 'evening' => 'Evening', 'distance' => 'Distance learning'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= old('study_mode') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if (has_error('study_mode')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('study_mode') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sponsor_type">Sponsorship</label>
                    <select class="form-select" id="sponsor_type" name="sponsor_type">
                        <option value="">— select —</option>
                        <?php foreach (['self' => 'Self-sponsored', 'government' => 'Government / HELB', 'scholarship' => 'Scholarship', 'employer' => 'Employer'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= old('sponsor_type') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>

            <!-- PERSONAL DETAILS -->
            <div class="section-title">Personal information</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="first_name">First name <span class="req">*</span></label>
                    <input type="text" class="form-control <?= has_error('first_name') ? 'is-invalid' : '' ?>"
                           id="first_name" name="first_name" value="<?= old('first_name') ?>" required>
                    <?php if (has_error('first_name')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('first_name') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="last_name">Last name <span class="req">*</span></label>
                    <input type="text" class="form-control <?= has_error('last_name') ? 'is-invalid' : '' ?>"
                           id="last_name" name="last_name" value="<?= old('last_name') ?>" required>
                    <?php if (has_error('last_name')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('last_name') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="other_name">Other name(s)</label>
                    <input type="text" class="form-control" id="other_name" name="other_name" value="<?= old('other_name') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="gender">Gender <span class="req">*</span></label>
                    <select class="form-select <?= has_error('gender') ? 'is-invalid' : '' ?>"
                            id="gender" name="gender" required>
                        <option value="">— select —</option>
                        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= old('gender') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if (has_error('gender')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('gender') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="date_of_birth">Date of birth <span class="req">*</span></label>
                    <input type="date" class="form-control <?= has_error('date_of_birth') ? 'is-invalid' : '' ?>"
                           id="date_of_birth" name="date_of_birth" value="<?= old('date_of_birth') ?>" required>
                    <?php if (has_error('date_of_birth')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('date_of_birth') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="nationality">Nationality <span class="req">*</span></label>
                    <input type="text" class="form-control <?= has_error('nationality') ? 'is-invalid' : '' ?>"
                           id="nationality" name="nationality" value="<?= old('nationality') ?>" placeholder="e.g. Kenyan" required>
                    <?php if (has_error('nationality')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('nationality') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="national_id">National ID / Passport No. <span class="req">*</span></label>
                    <input type="text" class="form-control <?= has_error('national_id') ? 'is-invalid' : '' ?>"
                           id="national_id" name="national_id" value="<?= old('national_id') ?>" required>
                    <?php if (has_error('national_id')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('national_id') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="email">Email address <span class="req">*</span></label>
                    <input type="email" class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>"
                           id="email" name="email" value="<?= old('email') ?>" required>
                    <?php if (has_error('email')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('email') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="phone">Phone number <span class="req">*</span></label>
                    <input type="tel" class="form-control <?= has_error('phone') ? 'is-invalid' : '' ?>"
                           id="phone" name="phone" value="<?= old('phone') ?>" placeholder="+254 7XX XXX XXX" required>
                    <?php if (has_error('phone')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('phone') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="address">Postal / physical address</label>
                    <input type="text" class="form-control" id="address" name="address" value="<?= old('address') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="county">County / Region</label>
                    <input type="text" class="form-control" id="county" name="county" value="<?= old('county') ?>">
                </div>
            </div>

            <!-- ACADEMIC BACKGROUND -->
            <div class="section-title">Academic background</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="previous_school">Previous institution</label>
                    <input type="text" class="form-control" id="previous_school" name="previous_school" value="<?= old('previous_school') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="qualification">Highest qualification</label>
                    <input type="text" class="form-control" id="qualification" name="qualification"
                           value="<?= old('qualification') ?>" placeholder="e.g. KCSE, A-Level, Diploma">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="grade_obtained">Grade / GPA</label>
                    <input type="text" class="form-control" id="grade_obtained" name="grade_obtained"
                           value="<?= old('grade_obtained') ?>" placeholder="e.g. B+, 3.6">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="year_completed">Year completed</label>
                    <input type="number" class="form-control" id="year_completed" name="year_completed"
                           value="<?= old('year_completed') ?>" min="1970" max="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
                </div>
            </div>

            <!-- PERSONAL STATEMENT -->
            <div class="section-title">Personal statement</div>
            <div class="mb-3">
                <label class="form-label" for="personal_statement">
                    Why do you want to pursue this programme? (max 2 000 characters)
                </label>
                <textarea class="form-control" id="personal_statement" name="personal_statement"
                          rows="5" maxlength="2000"><?= old('personal_statement') ?></textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <p class="text-muted small mb-0">
                    Already applied? <a href="<?= url('/apply/status') ?>">Check your status</a>.
                </p>
                <button type="submit" class="btn btn-primary px-4 py-2">
                    Submit application &rarr;
                </button>
            </div>
        </form>
    </div>

    <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
