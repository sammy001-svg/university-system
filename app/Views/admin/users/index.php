<?php
layout('layouts.app');
$pageTitle = 'User Accounts';
section('content');
$rows = $result['data'];
?>
<div class="page-head">
    <div>
        <h1>User Accounts</h1>
        <p class="lede">Every person who can sign in, and the roles that define what they may do.</p>
    </div>
    <?php if (can('users.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/admin/users/create') ?>">
            <?= icon('plus', 'ico-sm') ?> New user
        </a>
    <?php endif; ?>
</div>

<form class="filter-bar" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="search" name="q" class="form-control form-control-sm" value="<?= e($_GET['q'] ?? '') ?>"
                   placeholder="Name, username, email or phone">
        </div>
        <div class="col-md-2">
            <label class="form-label">Account type</label>
            <select name="user_type" class="form-select form-select-sm" data-auto-submit>
                <?= enum_options(['admin', 'staff', 'lecturer', 'student', 'parent'], $_GET['user_type'] ?? '', 'All types') ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Role</label>
            <select name="role_id" class="form-select form-select-sm" data-auto-submit>
                <?= options($roles, $_GET['role_id'] ?? '', 'All roles') ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm" data-auto-submit>
                <?= enum_options(['active', 'inactive', 'suspended', 'pending'], $_GET['status'] ?? '', 'Any status') ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-primary w-100">Apply</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <?= icon('users') ?> Accounts
        <span class="badge text-bg-light"><?= number_format((int) $result['total']) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>User</th><th>Username</th><th>Type</th><th>Roles</th>
                <th>Last sign-in</th><th>Status</th><th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('users') ?><p>No accounts match your filters.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $name = trim($row['first_name'] . ' ' . $row['last_name']); ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm" style="background:<?= e(avatar_color($name)) ?>">
                                <?php if (!empty($row['avatar'])): ?>
                                    <img src="<?= uploaded($row['avatar']) ?>" alt="">
                                <?php else: ?><?= e(initials($row['first_name'], $row['last_name'])) ?><?php endif; ?>
                            </span>
                            <div>
                                <div class="fw-semibold"><?= e($name) ?></div>
                                <div class="text-muted-sm"><?= e($row['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><code><?= e($row['username']) ?></code></td>
                    <td><?= e(humanize($row['user_type'])) ?></td>
                    <td class="text-muted-sm"><?= e($row['roles'] ?? 'None') ?></td>
                    <td class="text-muted-sm"><?= $row['last_login_at'] ? ago($row['last_login_at']) : 'Never' ?></td>
                    <td><?= status_badge($row['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('users.reset')): ?>
                            <form class="d-inline" method="post" action="<?= url('/admin/users/' . $row['id'] . '/reset-password') ?>"
                                  data-confirm="Reset this user's password and email them a temporary one?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-light" title="Reset password"><?= icon('shield', 'ico-sm') ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (can('users.edit')): ?>
                            <a class="btn btn-sm btn-light" title="Edit" href="<?= url('/admin/users/' . $row['id'] . '/edit') ?>">
                                <?= icon('edit', 'ico-sm') ?></a>
                        <?php endif; ?>
                        <?php if (can('users.delete') && (int) $row['id'] !== auth_id()): ?>
                            <form class="d-inline" method="post" action="<?= url('/admin/users/' . $row['id']) ?>"
                                  data-confirm="Delete this account? The person will no longer be able to sign in.">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-light text-danger" title="Delete"><?= icon('trash', 'ico-sm') ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ((int) $result['last_page'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center bg-white">
            <span class="text-muted-sm">Page <?= (int) $result['page'] ?> of <?= (int) $result['last_page'] ?></span>
            <?= paginate_links($result) ?>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
