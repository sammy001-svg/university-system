<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>My Account Profile</h1>
        <p class="lede">Manage your personal details and account settings.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4 text-center">
            <span class="avatar avatar-xl mx-auto mb-3" style="background:<?= e(avatar_color($user['first_name'].' '.$user['last_name'])) ?>">
                <?= e(initials($user['first_name'], $user['last_name'])) ?>
            </span>
            <h5 class="mb-1"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h5>
            <p class="text-muted mb-2">@<?= e($user['username']) ?></p>
            <?= status_badge($user['user_type']) ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('user') ?> Edit Profile Details</div>
            <form method="post" action="<?= url('/profile') ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?= e(old('first_name', $user['first_name'])) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?= e(old('last_name', $user['last_name'])) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= e(old('phone', $user['phone'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= e($user['email']) ?>" readonly disabled>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endsection(); ?>
