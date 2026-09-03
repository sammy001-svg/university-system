<?php
/** Main authenticated layout: sidebar + topbar + content. */
use App\Core\View;

$pageTitle = $pageTitle ?? 'Dashboard';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> &middot; <?= e($appName) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <?= View::section('head') ?>
</head>
<body>
<div class="app-shell">
    <?php partial('partials.sidebar'); ?>

    <div class="main">
        <?php partial('partials.topbar', ['pageTitle' => $pageTitle]); ?>

        <main class="content">
            <div class="print-header">
                <h3><?= e($appName) ?></h3>
                <div><?= e($pageTitle) ?> &middot; printed <?= date('d M Y H:i') ?></div>
            </div>

            <?php partial('partials.flash'); ?>
            <?= View::section('content') ?>
        </main>

        <footer class="text-center text-muted-sm py-3 no-print">
            &copy; <?= date('Y') ?> <?= e($appName) ?> &middot; Management System v<?= e(config('app.version')) ?>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= View::section('scripts') ?>
</body>
</html>
