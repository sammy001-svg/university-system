<?php
/** Renders one form control from its field definition. */
$name     = $field['name'];
$type     = $field['type'] ?? 'text';
$label    = $field['label'];
$required = (bool) ($field['required'] ?? false);
$value    = old_raw($name, $record[$name] ?? ($field['default'] ?? ''));
$invalid  = has_error($name) ? ' is-invalid' : '';
$attrs    = $field['attrs'] ?? '';
$id       = 'f_' . $name;
?>
<label class="form-label" for="<?= e($id) ?>">
    <?= e($label) ?><?php if ($required): ?> <span class="req">*</span><?php endif; ?>
</label>

<?php if ($type === 'select'): ?>
    <select class="form-select<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $attrs ?> <?= $required ? 'required' : '' ?>>
        <?= options($field['options'] ?? [], $value, $field['placeholder'] ?? ($required ? 'Select...' : 'None')) ?>
    </select>

<?php elseif ($type === 'enum'): ?>
    <select class="form-select<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" <?= $attrs ?> <?= $required ? 'required' : '' ?>>
        <?= enum_options($field['values'] ?? [], $value, $field['placeholder'] ?? null) ?>
    </select>

<?php elseif ($type === 'textarea'): ?>
    <textarea class="form-control<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
              rows="<?= (int) ($field['rows'] ?? 3) ?>" <?= $attrs ?>><?= e((string) $value) ?></textarea>

<?php elseif ($type === 'checkbox'): ?>
    <div class="form-check mt-1">
        <input type="hidden" name="<?= e($name) ?>" value="0">
        <input class="form-check-input" type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1"
               <?= (string) $value === '1' ? 'checked' : '' ?>>
        <label class="form-check-label text-muted-sm" for="<?= e($id) ?>">
            <?= e($field['checkboxLabel'] ?? 'Yes') ?>
        </label>
    </div>

<?php elseif ($type === 'file'): ?>
    <input type="file" class="form-control<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
           accept="<?= e($field['accept'] ?? '') ?>" <?= $attrs ?>>
    <?php if (!empty($record[$name])): ?>
        <div class="text-muted-sm mt-1">Current: <a href="<?= uploaded($record[$name]) ?>" target="_blank">view file</a></div>
    <?php endif; ?>

<?php elseif ($type === 'static'): ?>
    <div class="form-control-plaintext"><?= e((string) $value) ?></div>

<?php else: ?>
    <input type="<?= e($type) ?>" class="form-control<?= $invalid ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
           value="<?= e((string) $value) ?>"
           placeholder="<?= e($field['placeholder'] ?? '') ?>"
           <?= isset($field['step']) ? 'step="' . e((string) $field['step']) . '"' : '' ?>
           <?= isset($field['min']) ? 'min="' . e((string) $field['min']) . '"' : '' ?>
           <?= isset($field['max']) ? 'max="' . e((string) $field['max']) . '"' : '' ?>
           <?= $attrs ?> <?= $required ? 'required' : '' ?>>
<?php endif; ?>

<?php if (has_error($name)): ?>
    <div class="invalid-feedback d-block"><?= error_for($name) ?></div>
<?php elseif (!empty($field['help'])): ?>
    <div class="form-text text-muted-sm"><?= e($field['help']) ?></div>
<?php endif; ?>
