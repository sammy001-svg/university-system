<?php
layout('layouts.auth');
$pageTitle = 'Careers';
section('content');
$v = $vacancy;
?>
<p class="mb-1"><a class="small" href="<?= url('/careers') ?>">&larr; All positions</a></p>
<h1><?= e($v['title']) ?></h1>
<p class="lede"><?= e((string) ($v['department_name'] ?? 'University-wide')) ?> &middot; <?= e(humanize($v['employment_type'])) ?> &middot; <?= e(humanize($v['staff_category'])) ?>
    &middot; ref <?= e($v['reference']) ?> &middot; closes <strong><?= fdate($v['closing_date']) ?></strong></p>

<?php if ($done): ?>
    <div class="alert alert-success"><strong>Thank you - your application has been received.</strong> Shortlisted candidates will be contacted.</div>
<?php else: ?>
    <div class="small mb-3" style="white-space:pre-wrap"><?= e($v['description']) ?></div>
    <?php if ($v['requirements']): ?><h2 class="h6">Requirements</h2><div class="small mb-3" style="white-space:pre-wrap"><?= e($v['requirements']) ?></div><?php endif; ?>
    <h2 class="h6 mt-4">Apply</h2>
    <form method="post" enctype="multipart/form-data" action="<?= url('/careers/' . $v['id']) ?>">
        <?= csrf_field() ?>
        <div class="mb-2"><label class="form-label">Full name <span class="req">*</span></label><input class="form-control" name="full_name" required value="<?= old('full_name') ?>"></div>
        <div class="row g-2 mb-2">
            <div class="col-7"><label class="form-label">Email <span class="req">*</span></label><input class="form-control" type="email" name="email" required value="<?= old('email') ?>"></div>
            <div class="col-5"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= old('phone') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Highest qualification</label><input class="form-control" name="qualification" value="<?= old('qualification') ?>" placeholder="e.g. MSc Public Health, University of Liberia"></div>
        <div class="mb-2"><label class="form-label">Cover letter</label><textarea class="form-control" name="cover_letter" rows="5"><?= old('cover_letter') ?></textarea></div>
        <div class="mb-3"><label class="form-label">CV (PDF or Word, max 10 MB) <span class="req">*</span></label><input class="form-control" type="file" name="cv" accept=".pdf,.doc,.docx" required></div>
        <button class="btn btn-primary w-100">Submit application</button>
    </form>
<?php endif; ?>
<?php endsection(); ?>
