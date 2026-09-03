<?php
layout('layouts.app');
$pageTitle = 'Roles and Permissions';
section('content');
?>
<div class="page-head">
    <div>
        <h1>Roles and Permissions</h1>
        <p class="lede">Roles bundle permissions; users inherit everything from the roles they hold.</p>
    </div>
    <?php if (can('roles.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/admin/roles/create') ?>">
            <?= icon('plus', 'ico-sm') ?> New role
        </a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <?php foreach ($roles as $role): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <?= icon('shield') ?>
                    <span><?= e($role['name']) ?></span>
                    <?php if ((int) $role['is_system'] === 1): ?>
                        <span class="badge text-bg-light ms-auto">System</span>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted-sm flex-grow-1"><?= e($role['description'] ?? 'No description.') ?></p>
                    <div class="d-flex gap-3 mb-3">
                        <div>
                            <div class="fw-semibold"><?= (int) $role['users_count'] ?></div>
                            <div class="text-muted-sm">Users</div>
                        </div>
                        <div>
                            <div class="fw-semibold"><?= $role['slug'] === 'super-admin' ? 'All' : (int) $role['permissions_count'] ?></div>
                            <div class="text-muted-sm">Permissions</div>
                        </div>
                        <div>
                            <div class="fw-semibold">L<?= (int) $role['level'] ?></div>
                            <div class="text-muted-sm">Authority</div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (can('roles.assign')): ?>
                            <a class="btn btn-sm btn-outline-primary flex-grow-1" href="<?= url('/admin/roles/' . $role['id'] . '/permissions') ?>">
                                Permissions
                            </a>
                        <?php endif; ?>
                        <?php if (can('roles.edit')): ?>
                            <a class="btn btn-sm btn-light" href="<?= url('/admin/roles/' . $role['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?></a>
                        <?php endif; ?>
                        <?php if (can('roles.delete') && (int) $role['is_system'] === 0): ?>
                            <form method="post" action="<?= url('/admin/roles/' . $role['id']) ?>"
                                  data-confirm="Delete the <?= e($role['name']) ?> role?">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-light text-danger"><?= icon('trash', 'ico-sm') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endsection(); ?>
