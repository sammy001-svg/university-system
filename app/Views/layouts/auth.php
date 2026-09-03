<?php
/** Layout for the sign-in / password screens. */
use App\Core\View;

$pageTitle = $pageTitle ?? 'Sign in';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> &middot; <?= e($appName) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <aside class="auth-aside">
        <div>
            <div class="d-flex align-items-center gap-2 mb-5">
                <div class="crest" style="width:40px;height:40px;border-radius:10px;background:var(--accent);color:var(--brand-900);display:grid;place-items:center;font-weight:700">
                    <?= e(substr((string) $appShortName, 0, 2)) ?>
                </div>
                <div>
                    <div style="color:#fff;font-weight:600"><?= e($appName) ?></div>
                    <div style="font-size:11px;color:#94a3b8;letter-spacing:.5px;text-transform:uppercase">Management System</div>
                </div>
            </div>
            <h2>One system for the whole institution.</h2>
            <p>Admissions, registration, teaching, examinations, finance, library and accommodation &mdash; managed in a single secure platform.</p>

            <div class="feature"><?= icon('student') ?><div><strong>Student portal</strong><span>Register units, track results, view fee statements.</span></div></div>
            <div class="feature"><?= icon('clipboard') ?><div><strong>Lecturer workspace</strong><span>Class lists, attendance, coursework and marks.</span></div></div>
            <div class="feature"><?= icon('shield') ?><div><strong>Role-based access</strong><span>Granular permissions and a full audit trail.</span></div></div>
        </div>
        <div style="font-size:12px;color:#8ba0ba">&copy; <?= date('Y') ?> <?= e($appName) ?></div>
    </aside>

    <section class="auth-panel">
        <div class="auth-card">
            <?php partial('partials.flash'); ?>
            <?= View::section('content') ?>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
