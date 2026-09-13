<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1><?= e($isNew ? 'New Fee Structure' : 'Edit Fee Structure') ?></h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/finance/fee-structures') ?>">&larr; Back to Fee Structures</a>
</div>

<div class="card">
    <form method="post" action="<?= $isNew ? url('/finance/fee-structures') : url('/finance/fee-structures/' . ($record['id'] ?? '')) ?>">
        <?= csrf_field() ?>
        <?php if (!$isNew): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Fee Structure Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e(old('name', $record['name'] ?? '')) ?>" required>
                </div>
                <?php if ($isNew): ?>
                <div class="col-md-6">
                    <label class="form-label">Program</label>
                    <select name="program_id" class="form-select">
                        <?= options(array_column($programs, 'label', 'id'), old('program_id', $record['program_id'] ?? ''), '— All Programs —') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                    <select name="academic_year_id" class="form-select" required>
                        <?= options(array_column($academicYears, 'name', 'id'), old('academic_year_id', $record['academic_year_id'] ?? ''), 'Select Academic Year') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Study Mode <span class="text-danger">*</span></label>
                    <select name="study_mode" class="form-select" required>
                        <?= enum_options(['full_time','part_time','evening','distance'], old('study_mode', $record['study_mode'] ?? 'full_time')) ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Year of Study <span class="text-danger">*</span></label>
                    <select name="year_of_study" class="form-select" required>
                        <?= options([1=>'Year 1', 2=>'Year 2', 3=>'Year 3', 4=>'Year 4', 5=>'Year 5'], old('year_of_study', $record['year_of_study'] ?? 1)) ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Fee Structure' : 'Save Changes' ?></button>
            <a href="<?= url('/finance/fee-structures') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
