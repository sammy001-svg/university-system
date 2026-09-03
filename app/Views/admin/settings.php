<?php
layout('layouts.app');
$pageTitle = 'System Settings';
section('content');
?>
<div class="page-head">
    <div>
        <h1>System Settings</h1>
        <p class="lede">Institution details and the rules that govern academic, finance and library operations.</p>
    </div>
</div>

<form method="post" action="<?= url('/admin/settings') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="settings_present" value="1">

    <ul class="nav nav-tabs mb-3">
        <?php foreach (array_keys($grouped) as $group): ?>
            <li class="nav-item">
                <a class="nav-link <?= $group === $active ? 'active' : '' ?>" data-bs-toggle="tab"
                   href="#tab-<?= e($group) ?>"><?= e(humanize($group)) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($grouped as $group => $settings): ?>
            <div class="tab-pane fade <?= $group === $active ? 'show active' : '' ?>" id="tab-<?= e($group) ?>">
                <div class="card">
                    <div class="card-header"><?= icon('settings') ?> <?= e(humanize($group)) ?> settings</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($settings as $setting): ?>
                                <?php $key = $setting['setting_key']; ?>
                                <div class="col-md-<?= $setting['data_type'] === 'text' ? 12 : 6 ?>">
                                    <label class="form-label" for="s_<?= e($key) ?>"><?= e($setting['label'] ?? humanize($key)) ?></label>

                                    <?php if ($setting['data_type'] === 'boolean'): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="s_<?= e($key) ?>"
                                                   name="settings[<?= e($key) ?>]" value="1"
                                                   <?= (string) $setting['setting_value'] === '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label text-muted-sm" for="s_<?= e($key) ?>">Enabled</label>
                                        </div>
                                    <?php elseif ($setting['data_type'] === 'text'): ?>
                                        <textarea class="form-control" id="s_<?= e($key) ?>" rows="2"
                                                  name="settings[<?= e($key) ?>]"><?= e($setting['setting_value']) ?></textarea>
                                    <?php else: ?>
                                        <input class="form-control" id="s_<?= e($key) ?>" name="settings[<?= e($key) ?>]"
                                               type="<?= in_array($setting['data_type'], ['integer', 'decimal'], true) ? 'number' : 'text' ?>"
                                               <?= $setting['data_type'] === 'decimal' ? 'step="0.01"' : '' ?>
                                               value="<?= e($setting['setting_value']) ?>">
                                    <?php endif; ?>

                                    <?php if (!empty($setting['description'])): ?>
                                        <div class="form-text text-muted-sm"><?= e($setting['description']) ?></div>
                                    <?php endif; ?>
                                    <div class="form-text text-muted-sm"><code><?= e($key) ?></code></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-3">
        <button class="btn btn-primary" type="submit">Save settings</button>
    </div>
</form>
<?php endsection(); ?>
