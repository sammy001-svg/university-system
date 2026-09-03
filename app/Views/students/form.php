<?php
layout('layouts.app');
$pageTitle = $isNew ? 'Admit a student' : 'Edit student record';
section('content');
$action = $isNew ? url('/students') : url('/students/' . $student['id']);

/** Small helper so this long form stays readable. */
$field = static function (string $name, string $label, array $opts = []) use ($student): void {
    $type     = $opts['type'] ?? 'text';
    $width    = $opts['width'] ?? 4;
    $required = $opts['required'] ?? false;
    $value    = old_raw($name, $student[$name] ?? ($opts['default'] ?? ''));
    echo '<div class="col-md-' . (int) $width . '">';
    echo '<label class="form-label" for="f_' . e($name) . '">' . e($label)
       . ($required ? ' <span class="req">*</span>' : '') . '</label>';

    if ($type === 'select') {
        echo '<select class="form-select' . (has_error($name) ? ' is-invalid' : '') . '" id="f_' . e($name) . '" name="' . e($name) . '"'
           . ($required ? ' required' : '') . '>'
           . options($opts['options'] ?? [], $value, $opts['placeholder'] ?? ($required ? 'Select...' : 'None'))
           . '</select>';
    } elseif ($type === 'enum') {
        echo '<select class="form-select" id="f_' . e($name) . '" name="' . e($name) . '"' . ($required ? ' required' : '') . '>'
           . enum_options($opts['values'] ?? [], $value, $opts['placeholder'] ?? null) . '</select>';
    } elseif ($type === 'textarea') {
        echo '<textarea class="form-control" id="f_' . e($name) . '" name="' . e($name) . '" rows="2">' . e((string) $value) . '</textarea>';
    } elseif ($type === 'file') {
        echo '<input type="file" class="form-control" id="f_' . e($name) . '" name="' . e($name) . '" accept="image/*">';
    } else {
        echo '<input type="' . e($type) . '" class="form-control' . (has_error($name) ? ' is-invalid' : '') . '"'
           . ' id="f_' . e($name) . '" name="' . e($name) . '" value="' . e((string) $value) . '"'
           . (isset($opts['min']) ? ' min="' . (int) $opts['min'] . '"' : '')
           . (isset($opts['max']) ? ' max="' . (int) $opts['max'] . '"' : '')
           . (isset($opts['placeholder']) ? ' placeholder="' . e($opts['placeholder']) . '"' : '')
           . ($required ? ' required' : '') . '>';
    }
    if (has_error($name)) {
        echo '<div class="invalid-feedback d-block">' . error_for($name) . '</div>';
    } elseif (!empty($opts['help'])) {
        echo '<div class="form-text text-muted-sm">' . e($opts['help']) . '</div>';
    }
    echo '</div>';
};
?>
<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/students') ?>">&larr; Back to students</a></nav>
        <h1><?= e($pageTitle) ?></h1>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <div class="form-section-title">Personal details</div>
                    <div class="row g-3">
                        <?php
                        $field('first_name', 'First name', ['width' => 4, 'required' => true]);
                        $field('last_name', 'Last name', ['width' => 4, 'required' => true]);
                        $field('other_name', 'Other names', ['width' => 4]);
                        $field('gender', 'Gender', ['type' => 'enum', 'values' => ['male', 'female', 'other'], 'placeholder' => 'Not specified', 'width' => 3]);
                        $field('date_of_birth', 'Date of birth', ['type' => 'date', 'width' => 3]);
                        $field('national_id', 'National ID / passport', ['width' => 3]);
                        $field('nationality', 'Nationality', ['width' => 3, 'default' => 'Liberian']);
                        $field('avatar', 'Photograph', ['type' => 'file', 'width' => 6]);
                        ?>
                    </div>

                    <div class="form-section-title">Contact</div>
                    <div class="row g-3">
                        <?php
                        $field('email', 'Email address', ['type' => 'email', 'width' => 4, 'required' => true]);
                        $field('phone', 'Phone number', ['width' => 4]);
                        $field('city', 'City / town', ['width' => 4]);
                        $field('county', 'County', ['width' => 4]);
                        $field('postal_address', 'Postal address', ['width' => 4]);
                        $field('physical_address', 'Physical address', ['width' => 4]);
                        ?>
                    </div>

                    <div class="form-section-title">Emergency contact</div>
                    <div class="row g-3">
                        <?php
                        $field('emergency_contact_name', 'Contact name', ['width' => 4]);
                        $field('emergency_contact_phone', 'Contact phone', ['width' => 4]);
                        $field('emergency_contact_relation', 'Relationship', ['width' => 4]);
                        ?>
                    </div>

                    <div class="form-section-title">Academic placement</div>
                    <div class="row g-3">
                        <?php
                        $field('program_id', 'Programme', ['type' => 'select', 'options' => $programs, 'width' => 6, 'required' => true]);
                        $field('intake_id', 'Intake', ['type' => 'select', 'options' => $intakes, 'width' => 3]);
                        $field('study_mode', 'Study mode', ['type' => 'enum', 'values' => ['full_time', 'part_time', 'evening', 'distance'], 'width' => 3, 'required' => true]);
                        $field('year_of_study', 'Year of study', ['type' => 'number', 'min' => 1, 'max' => 6, 'width' => 3, 'required' => true, 'default' => 1]);
                        $field('current_semester', 'Current semester', ['type' => 'number', 'min' => 1, 'max' => 4, 'width' => 3, 'required' => true, 'default' => 1]);
                        $field('admission_number', 'Admission number', ['width' => 3, 'help' => $isNew ? 'Leave blank to generate automatically.' : null]);
                        $field('registration_number', 'Registration number', ['width' => 3]);
                        $field('admission_date', 'Admission date', ['type' => 'date', 'width' => 3]);
                        if (!$isNew) {
                            $field('status', 'Status', [
                                'type'     => 'enum',
                                'values'   => ['active', 'deferred', 'suspended', 'graduated', 'withdrawn', 'expelled', 'alumni'],
                                'width'    => 3,
                                'required' => true,
                                'default'  => 'active',
                            ]);
                        }
                        ?>
                    </div>

                    <div class="form-section-title">Sponsorship</div>
                    <div class="row g-3">
                        <?php
                        $field('sponsor_type', 'Sponsor type', [
                            'type'    => 'enum',
                            'values'  => ['self', 'government', 'scholarship', 'employer', 'parent', 'other'],
                            'width'   => 4,
                            'default' => 'self',
                        ]);
                        $field('sponsor_name', 'Sponsor name', ['width' => 8]);
                        ?>
                    </div>

                    <div class="form-section-title">Previous education</div>
                    <div class="row g-3">
                        <?php
                        $field('previous_school', 'Previous institution', ['width' => 6]);
                        $field('previous_qualification', 'Qualification', ['width' => 3, 'placeholder' => 'e.g. WASSCE']);
                        $field('previous_grade', 'Grade obtained', ['width' => 3]);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-header"><?= icon('check') ?> Save</div>
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <?= $isNew ? 'Admit student' : 'Save changes' ?>
                    </button>
                    <a href="<?= url('/students') ?>" class="btn btn-outline-secondary w-100">Cancel</a>

                    <?php if ($isNew): ?>
                        <div class="divider"></div>
                        <div class="text-muted-sm">
                            A portal account is created automatically and the temporary
                            password is emailed to the student.
                        </div>
                    <?php elseif (!empty($student['created_at'])): ?>
                        <div class="divider"></div>
                        <div class="text-muted-sm">
                            <div>Created <?= fdatetime($student['created_at']) ?></div>
                            <?php if (!empty($student['updated_at'])): ?>
                                <div>Updated <?= ago($student['updated_at']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endsection(); ?>
