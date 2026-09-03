<?php
layout('layouts.app');
$pageTitle = $isNew ? 'New user account' : 'Edit user account';
section('content');
$action = $isNew ? url('/admin/users') : url('/admin/users/' . $user['id']);
?>
<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/admin/users') ?>">&larr; Back to user accounts</a></nav>
        <h1><?= e($pageTitle) ?></h1>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><?= icon('users') ?> Personal details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label" for="title">Title</label>
                            <input class="form-control" id="title" name="title" value="<?= old('title', $user['title'] ?? '') ?>" placeholder="Dr">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="first_name">First name <span class="req">*</span></label>
                            <input class="form-control <?= has_error('first_name') ? 'is-invalid' : '' ?>" id="first_name"
                                   name="first_name" value="<?= old('first_name', $user['first_name'] ?? '') ?>" required>
                            <?php if (has_error('first_name')): ?><div class="invalid-feedback d-block"><?= error_for('first_name') ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="last_name">Last name <span class="req">*</span></label>
                            <input class="form-control <?= has_error('last_name') ? 'is-invalid' : '' ?>" id="last_name"
                                   name="last_name" value="<?= old('last_name', $user['last_name'] ?? '') ?>" required>
                            <?php if (has_error('last_name')): ?><div class="invalid-feedback d-block"><?= error_for('last_name') ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="other_name">Other names</label>
                            <input class="form-control" id="other_name" name="other_name" value="<?= old('other_name', $user['other_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="gender">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <?= enum_options(['male', 'female', 'other'], old_raw('gender', $user['gender'] ?? ''), 'Not specified') ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="date_of_birth">Date of birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                   value="<?= old('date_of_birth', $user['date_of_birth'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-section-title">Contact and sign-in</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email address <span class="req">*</span></label>
                            <input type="email" class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>" id="email"
                                   name="email" value="<?= old('email', $user['email'] ?? '') ?>" required>
                            <?php if (has_error('email')): ?><div class="invalid-feedback d-block"><?= error_for('email') ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone number</label>
                            <input class="form-control" id="phone" name="phone" value="<?= old('phone', $user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="username">Username <span class="req">*</span></label>
                            <input class="form-control <?= has_error('username') ? 'is-invalid' : '' ?>" id="username"
                                   name="username" value="<?= old('username', $user['username'] ?? '') ?>" required>
                            <?php if (has_error('username')): ?><div class="invalid-feedback d-block"><?= error_for('username') ?></div><?php endif; ?>
                        </div>
                        <?php if ($isNew): ?>
                            <div class="col-md-6">
                                <label class="form-label" for="password">Temporary password</label>
                                <input class="form-control" id="password" name="password" placeholder="Leave blank to generate one">
                                <div class="form-text text-muted-sm">Emailed to the user; they must change it at first sign-in.</div>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label" for="user_type">Account type <span class="req">*</span></label>
                            <select class="form-select" id="user_type" name="user_type" required>
                                <?= enum_options(['admin', 'staff', 'lecturer', 'student', 'parent'], old_raw('user_type', $user['user_type'] ?? 'staff')) ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="status">Status <span class="req">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <?= enum_options(['active', 'inactive', 'suspended', 'pending'], old_raw('status', $user['status'] ?? 'active')) ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><?= icon('shield') ?> Roles</div>
                <div class="card-body" style="max-height:460px;overflow:auto">
                    <?php foreach ($roles as $role): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>"
                                   id="role<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $userRoles, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="role<?= (int) $role['id'] ?>">
                                <span class="fw-semibold"><?= e($role['name']) ?></span>
                                <span class="text-muted-sm d-block"><?= e(str_limit($role['description'], 60)) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <button class="btn btn-primary w-100 mb-2" type="submit">
                        <?= $isNew ? 'Create account' : 'Save changes' ?>
                    </button>
                    <a class="btn btn-outline-secondary w-100" href="<?= url('/admin/users') ?>">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endsection(); ?>
