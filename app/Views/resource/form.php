<?php
/**
 * Generic create/edit form.
 * Expects: title, iconName, entity, routeBase, fields, record, isNew
 */
layout('layouts.app');
section('content');

$action = $isNew ? url($routeBase) : url($routeBase . '/' . $record['id']);
?>
<div class="page-head">
    <div>
        <nav class="breadcrumb">
            <a href="<?= url($routeBase) ?>">&larr; Back to <?= e(strtolower($entity)) ?> list</a>
        </nav>
        <h1><?= e($title) ?></h1>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header"><?= icon($iconName) ?> <?= e($entity) ?> details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php $group = null; ?>
                        <?php foreach ($fields as $field): ?>
                            <?php if (($field['group'] ?? null) !== null && $field['group'] !== $group): ?>
                                <?php $group = $field['group']; ?>
                                <div class="col-12"><div class="form-section-title"><?= e($group) ?></div></div>
                            <?php endif; ?>
                            <div class="col-md-<?= (int) ($field['width'] ?? 6) ?>">
                                <?php partial('resource.field', ['field' => $field, 'record' => $record]); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-header"><?= icon('check') ?> Save</div>
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <?= $isNew ? 'Create ' . e($entity) : 'Save changes' ?>
                    </button>
                    <a href="<?= url($routeBase) ?>" class="btn btn-outline-secondary w-100">Cancel</a>

                    <?php if (!$isNew && !empty($record['created_at'])): ?>
                        <div class="divider"></div>
                        <div class="text-muted-sm">
                            <div>Created <?= fdatetime($record['created_at']) ?></div>
                            <?php if (!empty($record['updated_at'])): ?>
                                <div>Updated <?= ago($record['updated_at']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endsection(); ?>
