<?php
layout('layouts.app');
$pageTitle = 'System Settings';
section('content');
?>
<div class="page-head">
    <div>
        <h1>System Settings</h1>
        <p class="lede">Configure institution details, logo branding, and rules for academic, financial, and operational modules.</p>
    </div>
</div>

<form method="post" action="<?= url('/admin/settings') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="settings_present" value="1">

    <ul class="nav nav-tabs mb-3" role="tablist">
        <?php foreach (array_keys($grouped) as $i => $group): ?>
            <li class="nav-item">
                <button type="button" class="nav-link <?= $group === $active ? 'active' : '' ?>"
                        data-bs-toggle="tab" data-bs-target="#tab-<?= e($group) ?>">
                    <?= e(humanize($group)) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($grouped as $group => $settings): ?>
            <div class="tab-pane fade <?= $group === $active ? 'show active' : '' ?>" id="tab-<?= e($group) ?>">
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center gap-2">
                        <?= icon('settings') ?>
                        <span class="fw-bold"><?= e(humanize($group)) ?> Settings</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($settings as $setting): ?>
                                <?php $key = $setting['setting_key']; ?>

                                <?php if ($setting['data_type'] === 'file' || $key === 'institution_logo'): ?>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold" for="s_<?= e($key) ?>"><?= e($setting['label'] ?? 'Institution Logo') ?></label>
                                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border">
                                            <div class="bg-white p-2 border rounded text-center" style="min-width: 80px; min-height: 80px; display: flex; align-items: center; justify-content: center;">
                                                <?php if (!empty($setting['setting_value'])): ?>
                                                    <img src="<?= uploaded($setting['setting_value']) ?>" alt="Logo" style="max-height: 64px; max-width: 120px; object-fit: contain;">
                                                <?php else: ?>
                                                    <span class="text-muted-sm fs-4 fw-bold"><?= e(substr((string)($appShortName ?? 'BBU'), 0, 2)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <input class="form-control" type="file" id="s_<?= e($key) ?>" name="<?= e($key) ?>" accept="image/*">
                                                <div class="form-text text-muted-sm">Upload PNG, JPG, GIF or WebP logo (max 4 MB). Recommended size: 200x200px.</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($setting['data_type'] === 'boolean'): ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="s_<?= e($key) ?>"><?= e($setting['label'] ?? humanize($key)) ?></label>
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" id="s_<?= e($key) ?>"
                                                   name="settings[<?= e($key) ?>]" value="1"
                                                   <?= (string) $setting['setting_value'] === '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="s_<?= e($key) ?>">Enabled</label>
                                        </div>
                                        <?php if (!empty($setting['description'])): ?>
                                            <div class="form-text text-muted-sm"><?= e($setting['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($setting['data_type'] === 'text'): ?>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold" for="s_<?= e($key) ?>"><?= e($setting['label'] ?? humanize($key)) ?></label>
                                        <textarea class="form-control" id="s_<?= e($key) ?>" rows="3"
                                                  name="settings[<?= e($key) ?>]"><?= e($setting['setting_value']) ?></textarea>
                                        <?php if (!empty($setting['description'])): ?>
                                            <div class="form-text text-muted-sm"><?= e($setting['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="s_<?= e($key) ?>"><?= e($setting['label'] ?? humanize($key)) ?></label>
                                        <input class="form-control" id="s_<?= e($key) ?>" name="settings[<?= e($key) ?>]"
                                               type="<?= in_array($setting['data_type'], ['integer', 'decimal'], true) ? 'number' : 'text' ?>"
                                               <?= $setting['data_type'] === 'decimal' ? 'step="0.01"' : '' ?>
                                               value="<?= e($setting['setting_value']) ?>">
                                        <?php if (!empty($setting['description'])): ?>
                                            <div class="form-text text-muted-sm"><?= e($setting['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card p-3 d-flex flex-row justify-content-between align-items-center bg-white shadow-sm">
        <span class="text-muted-sm">All changes will be applied instantly across system portals.</span>
        <button class="btn btn-primary btn-lg" type="submit"><?= icon('check', 'ico-sm') ?> Save All Settings</button>
    </div>
</form>
<?php endsection(); ?>
