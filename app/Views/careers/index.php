<?php
layout('layouts.auth');
$pageTitle = 'Careers';
section('content');
?>
<h1>Careers at <?= e($appName) ?></h1>
<p class="lede">Join our community of educators and professionals. Open positions are listed below.</p>
<?php if ($vacancies === []): ?>
    <div class="alert alert-info">There are no open positions at the moment. Please check again soon.</div>
<?php endif; ?>
<?php foreach ($vacancies as $v): ?>
    <a class="d-block p-3 mb-2 rounded border text-decoration-none" style="color:inherit" href="<?= url('/careers/' . $v['id']) ?>">
        <div class="fw-semibold"><?= e($v['title']) ?></div>
        <div class="text-muted-sm"><?= e((string) ($v['department_name'] ?? 'University-wide')) ?> &middot; <?= e(humanize($v['employment_type'])) ?> &middot; closes <?= fdate($v['closing_date']) ?></div>
    </a>
<?php endforeach; ?>
<p class="text-muted-sm mt-3 mb-0"><a href="<?= url('/login') ?>">&larr; Sign in</a></p>
<?php endsection(); ?>
