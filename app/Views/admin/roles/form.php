<?php
layout('layouts.app');
$pageTitle = $isNew ? 'New role' : 'Edit role';
section('content');
$action = $isNew ? url('/admin/roles') : url('/admin/roles/' . $role['id']);
?>
<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/admin/roles') ?>">&larr; Back to roles</a></nav>
        <h1><?= e($pageTitle) ?></h1>
    </div>
</div>

<form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><?= icon('shield') ?> Role details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Role name <span class="req">*</span></label>
                            <input class="form-control" id="name" name="name" value="<?= old('name', $role['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="level">Authority level <span class="req">*</span></label>
                            <input type="number" class="form-control" id="level" name="level" min="1" max="99"
                                   value="<?= old('level', $role['level'] ?? 10) ?>" required>
                            <div class="form-text text-muted-sm">1 is the highest authority.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="slug">Slug</label>
                            <input class="form-control" id="slug" name="slug" value="<?= old('slug', $role['slug'] ?? '') ?>"
                                   <?= !empty($role['is_system']) ? 'readonly' : '' ?> placeholder="auto-generated">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= old('description', $role['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <button class="btn btn-primary w-100 mb-2" type="submit"><?= $isNew ? 'Create role' : 'Save changes' ?></button>
                    <a class="btn btn-outline-secondary w-100" href="<?= url('/admin/roles') ?>">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endsection(); ?>
