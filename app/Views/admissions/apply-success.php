<?php
/**
 * Application submitted successfully — confirmation page.
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
        .success-wrap { max-width: 560px; margin: 6rem auto; padding: 0 1rem; text-align: center; }
        .success-icon {
            width: 80px; height: 80px; border-radius: 50%;
            background: #dcfce7; display: inline-grid; place-items: center;
            font-size: 2.5rem; margin-bottom: 1.5rem;
        }
        .app-number {
            font-family: monospace; font-size: 1.4rem; font-weight: 700;
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 8px; padding: .5rem 1.2rem; display: inline-block;
            color: var(--accent, #3b82f6); margin: 1rem 0;
        }
        .success-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 2.5rem;
        }
    </style>
</head>
<body>
<div class="success-wrap">
    <div class="success-card">
        <div class="success-icon">✅</div>
        <h1 class="h3">Application received!</h1>
        <p class="text-muted">
            Thank you, <strong><?= e($firstName) ?></strong>. Your application to
            <strong><?= e($appName) ?></strong> has been submitted successfully.
        </p>
        <p class="text-muted small">Your application number is:</p>
        <div class="app-number"><?= e($applicationNumber) ?></div>
        <p class="text-muted small mt-3">
            <strong>Save this number.</strong> You will need it to track the status of your application.
        </p>
        <hr>
        <div class="d-flex flex-column gap-2 mt-3">
            <a href="<?= url('/apply/status') ?>" class="btn btn-primary">Check application status</a>
            <a href="<?= url('/apply') ?>" class="btn btn-outline-secondary">Submit another application</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
