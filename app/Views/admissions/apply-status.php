<?php
/**
 * Application status lookup page.
 */
$appName = $appName ?? 'University';

$statusLabel = [
    'pending'   => ['label' => 'Under review',  'class' => 'warning'],
    'reviewed'  => ['label' => 'Reviewed',       'class' => 'info'],
    'accepted'  => ['label' => 'Accepted',        'class' => 'success'],
    'rejected'  => ['label' => 'Not successful', 'class' => 'danger'],
    'enrolled'  => ['label' => 'Enrolled',        'class' => 'success'],
    'withdrawn' => ['label' => 'Withdrawn',       'class' => 'secondary'],
];
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
        .status-wrap { max-width: 560px; margin: 4rem auto; padding: 0 1rem; }
        .status-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 2rem;
        }
        .status-card h1 { font-size: 1.4rem; font-weight: 700; margin-bottom: .25rem; }
        .status-card .lede { color: #64748b; margin-bottom: 1.5rem; font-size: .95rem; }
        .result-row { display: flex; justify-content: space-between; padding: .5rem 0;
                      border-bottom: 1px solid #f1f5f9; font-size: .9rem; }
        .result-row:last-child { border-bottom: none; }
        .result-label { color: #64748b; }
        .status-icon { font-size: 2.5rem; }
    </style>
</head>
<body>
<div class="status-wrap">

    <div class="mb-3 text-center">
        <a href="<?= url('/apply') ?>" class="text-muted small">&larr; Back to application form</a>
    </div>

    <div class="status-card">
        <?php partial('partials.flash'); ?>

        <?php if ($application === null): ?>
            <h1>Check your application</h1>
            <p class="lede">Enter your application number and the email address you used when applying.</p>

            <form method="post" action="<?= url('/apply/status') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="application_number">
                        Application number <span class="req">*</span>
                    </label>
                    <input type="text" class="form-control <?= has_error('application_number') ? 'is-invalid' : '' ?>"
                           id="application_number" name="application_number"
                           value="<?= old('application_number') ?>"
                           placeholder="e.g. APP-2025-00001" autofocus required>
                    <?php if (has_error('application_number')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('application_number') ?></div>
                    <?php endif ?>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="email">Email address <span class="req">*</span></label>
                    <input type="email" class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>"
                           id="email" name="email" value="<?= old('email') ?>" required>
                    <?php if (has_error('email')): ?>
                        <div class="invalid-feedback d-block"><?= error_for('email') ?></div>
                    <?php endif ?>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">Check status</button>
            </form>

        <?php else:
            $st    = $application['status'] ?? 'pending';
            $badge = $statusLabel[$st] ?? ['label' => ucfirst($st), 'class' => 'secondary'];
        ?>
            <div class="text-center mb-4">
                <div class="status-icon">
                    <?php if ($st === 'accepted' || $st === 'enrolled'): ?>🎉
                    <?php elseif ($st === 'rejected'): ?>❌
                    <?php else: ?>📋
                    <?php endif ?>
                </div>
                <span class="badge bg-<?= $badge['class'] ?> fs-6 mt-2"><?= $badge['label'] ?></span>
            </div>

            <h1><?= e($application['first_name']) ?> <?= e($application['last_name']) ?></h1>
            <p class="lede">Here is the current status of your application.</p>

            <div class="mb-3">
                <div class="result-row">
                    <span class="result-label">Application number</span>
                    <strong><?= e($application['application_number']) ?></strong>
                </div>
                <div class="result-row">
                    <span class="result-label">Programme</span>
                    <span><?= e($application['program_name']) ?></span>
                </div>
                <div class="result-row">
                    <span class="result-label">Intake</span>
                    <span><?= e($application['intake_name']) ?></span>
                </div>
                <div class="result-row">
                    <span class="result-label">Submitted</span>
                    <span><?= $application['submitted_at'] ? e(date('d M Y', strtotime((string)$application['submitted_at']))) : '—' ?></span>
                </div>
                <?php if (!empty($application['remarks'])): ?>
                <div class="result-row">
                    <span class="result-label">Remarks</span>
                    <span><?= e($application['remarks']) ?></span>
                </div>
                <?php endif ?>
            </div>

            <?php if ($st === 'accepted'): ?>
                <div class="alert alert-success">
                    <strong>Congratulations!</strong> Your application has been accepted.
                    Please visit the admissions office to complete your enrolment.
                </div>
            <?php elseif ($st === 'rejected'): ?>
                <div class="alert alert-warning">
                    Unfortunately your application was not successful this time.
                    You may re-apply in a future intake.
                </div>
            <?php elseif ($st === 'pending' || $st === 'reviewed'): ?>
                <div class="alert alert-info">
                    Your application is being reviewed. We will notify you once a decision has been made.
                </div>
            <?php endif ?>

            <a href="<?= url('/apply/status') ?>" class="btn btn-outline-secondary w-100 mt-2">
                Check another application
            </a>
        <?php endif ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
