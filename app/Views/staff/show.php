<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1><?= e($staff['title'] ?? '') ?> <?= e($staff['first_name']) ?> <?= e($staff['last_name']) ?></h1>
        <p class="lede"><?= e($staff['staff_number']) ?> &middot; <?= e(humanize($staff['staff_category'] ?? '')) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('staff.edit')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('/staff/' . $staff['id'] . '/edit') ?>"><?= icon('edit','ico-sm') ?> Edit</a>
        <?php endif; ?>
        <a class="btn btn-sm btn-light" href="<?= url('/staff') ?>">&larr; Back</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4 text-center">
            <span class="avatar avatar-xl mx-auto mb-3" style="background:<?= e(avatar_color($staff['first_name'].' '.$staff['last_name'])) ?>">
                <?php if (!empty($staff['avatar'])): ?>
                    <img src="<?= uploaded($staff['avatar']) ?>" alt="">
                <?php else: ?><?= e(initials($staff['first_name'], $staff['last_name'])) ?><?php endif; ?>
            </span>
            <h5 class="mb-1"><?= e(($staff['title'] ?? '').' '.$staff['first_name'].' '.$staff['last_name']) ?></h5>
            <p class="text-muted mb-3"><?= e($staff['designation'] ?? '') ?></p>
            <?= status_badge($staff['status']) ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('staff') ?> Staff Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Staff Number</dt><dd class="col-sm-8"><code><?= e($staff['staff_number']) ?></code></dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($staff['email']) ?></dd>
                    <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= e($staff['phone'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Department</dt><dd class="col-sm-8"><?= e($staff['department_name'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Faculty</dt><dd class="col-sm-8"><?= e($staff['faculty_name'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Category</dt><dd class="col-sm-8"><?= status_badge($staff['staff_category'] ?? '') ?></dd>
                    <dt class="col-sm-4">Employment Type</dt><dd class="col-sm-8"><?= e(humanize($staff['employment_type'] ?? '')) ?></dd>
                    <dt class="col-sm-4">Date Joined</dt><dd class="col-sm-8"><?= date_fmt($staff['date_joined'] ?? '') ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
