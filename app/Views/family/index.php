<?php
layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div><h1>My students</h1><p class="lede">The students linked to your account. Choose one to see their progress and fees.</p></div>
</div>
<?php if ($students === []): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><?= icon('student') ?>
        <p>No student is linked to your account yet. Please contact the Office of Admission and Records.</p></div></div></div>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($students as $s): ?>
        <div class="col-md-6 col-xl-4">
            <a class="course-card" href="<?= url('/family/' . $s['id']) ?>">
                <div class="course-card-band"></div>
                <div class="course-card-body d-flex gap-3 align-items-center">
                    <span class="avatar avatar-lg" style="background:<?= e(avatar_color($s['first_name'] . ' ' . $s['last_name'])) ?>"><?= e(initials($s['first_name'], $s['last_name'])) ?></span>
                    <div><h3 class="mb-1"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></h3>
                        <div class="text-muted-sm"><?= e($s['admission_number']) ?> &middot; <?= e($s['program_name']) ?></div>
                        <div class="text-muted-sm">Your <?= e($s['relationship']) ?> relationship</div></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php endsection(); ?>
