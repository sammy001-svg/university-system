<?php
use App\Core\Auth;

$titles = [
    403 => 'Access denied',
    404 => 'Page not found',
    405 => 'Method not allowed',
    419 => 'Session expired',
    422 => 'Could not process that',
    429 => 'Too many requests',
    500 => 'Something went wrong',
];
$heading = $titles[$status] ?? 'Unexpected error';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= (int) $status ?> &middot; <?= e($heading) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;padding:24px">
    <div class="card" style="max-width:620px;width:100%">
        <div class="card-body p-4 p-md-5 text-center">
            <div style="font-size:56px;font-weight:700;color:var(--brand-800);line-height:1"><?= (int) $status ?></div>
            <h1 class="h4 mt-2 mb-2"><?= e($heading) ?></h1>
            <p class="text-muted mb-4"><?= e($message) ?></p>

            <div class="d-flex gap-2 justify-content-center">
                <a href="<?= url(Auth::check() ? '/dashboard' : '/login') ?>" class="btn btn-primary">
                    <?= Auth::check() ? 'Back to dashboard' : 'Go to sign in' ?>
                </a>
                <button class="btn btn-outline-secondary" onclick="history.back()">Go back</button>
            </div>

            <?php if (!empty($exception)): ?>
                <div class="text-start mt-4">
                    <div class="form-section-title">Debug detail</div>
                    <pre class="small bg-light p-3 rounded" style="max-height:280px;overflow:auto"><?= e(get_class($exception)) ?>
<?= e($exception->getMessage()) ?>

<?= e($exception->getFile()) ?>:<?= (int) $exception->getLine() ?>

<?= e($exception->getTraceAsString()) ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
