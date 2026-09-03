<?php
layout('layouts.app');
$pageTitle = 'Permissions: ' . $role['name'];
section('content');
?>
<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/admin/roles') ?>">&larr; Back to roles</a></nav>
        <h1>Permissions &mdash; <?= e($role['name']) ?></h1>
        <p class="lede">Tick everything holders of this role should be able to do.</p>
    </div>
</div>

<?php if ($role['slug'] === 'super-admin'): ?>
    <div class="alert alert-info d-flex gap-2">
        <?= icon('shield') ?>
        <div>The super administrator role implicitly holds every permission, including any added later.</div>
    </div>
<?php endif; ?>

<form method="post" action="<?= url('/admin/roles/' . $role['id'] . '/permissions') ?>">
    <?= csrf_field() ?>

    <div class="d-flex gap-2 mb-3">
        <input class="form-control form-control-sm" style="max-width:280px" placeholder="Filter permissions..."
               data-table-filter="#permission-grid">
        <button class="btn btn-sm btn-outline-secondary" type="button" id="selectAll">Select all</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" id="clearAll">Clear all</button>
        <button class="btn btn-sm btn-primary ms-auto" type="submit">Save permissions</button>
    </div>

    <div class="row g-3" id="permission-grid">
        <?php foreach ($grouped as $module => $permissions): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-header">
                        <?= icon('settings') ?> <?= e(humanize($module)) ?>
                        <span class="badge text-bg-light ms-auto"><?= count($permissions) ?></span>
                    </div>
                    <div class="card-body py-2">
                        <?php foreach ($permissions as $permission): ?>
                            <div class="form-check py-1">
                                <input class="form-check-input perm-box" type="checkbox" name="permissions[]"
                                       value="<?= (int) $permission['id'] ?>" id="perm<?= (int) $permission['id'] ?>"
                                       <?= in_array((int) $permission['id'], $assigned, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="perm<?= (int) $permission['id'] ?>">
                                    <span><?= e(humanize(explode('.', $permission['slug'])[1] ?? $permission['slug'])) ?></span>
                                    <span class="text-muted-sm d-block"><?= e($permission['description'] ?? $permission['slug']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-3">
        <button class="btn btn-primary" type="submit">Save permissions</button>
        <a class="btn btn-outline-secondary" href="<?= url('/admin/roles') ?>">Cancel</a>
    </div>
</form>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
document.getElementById('selectAll').addEventListener('click', function () {
    document.querySelectorAll('.perm-box').forEach(function (b) { b.checked = true; });
});
document.getElementById('clearAll').addEventListener('click', function () {
    document.querySelectorAll('.perm-box').forEach(function (b) { b.checked = false; });
});
</script>
<?php endsection(); ?>
