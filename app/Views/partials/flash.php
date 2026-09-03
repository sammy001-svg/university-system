<?php
$flashMap = [
    'success' => ['success', 'check'],
    'error'   => ['danger',  'bell'],
    'warning' => ['warning', 'bell'],
    'info'    => ['info',    'bell'],
];
foreach ($flashMap as $key => [$class, $iconName]):
    $message = $flash[$key] ?? null;
    if ($message === null || $message === '') { continue; }
    ?>
    <div class="alert alert-<?= $class ?> alert-dismissible fade show d-flex align-items-start gap-2" role="alert" data-auto-dismiss>
        <?= icon($iconName) ?>
        <div class="flex-grow-1"><?= e($message) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
