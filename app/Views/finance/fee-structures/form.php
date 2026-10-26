<?php layout('layouts.app'); section('content'); ?>
<?php
$scope = old('scope', match (true) {
    !empty($record['program_id'])    => 'program',
    !empty($record['department_id']) => 'department',
    !empty($record['faculty_id'])    => 'faculty',
    default                          => 'institution',
});
?>
<div class="page-head">
    <div>
        <h1><?= e($isNew ? 'New Fee Structure' : 'Edit Fee Structure') ?></h1>
        <p class="lede">Bill a college, department or programme, for new or continuing students. The most specific matching bill is used for each student.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/finance/fee-structures') ?>">&larr; Back to Fee Structures</a>
</div>

<div class="card">
    <form method="post" action="<?= $isNew ? url('/finance/fee-structures') : url('/finance/fee-structures/' . ($record['id'] ?? '')) ?>">
        <?= csrf_field() ?>
        <?php if (!$isNew): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Fee Structure Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e(old('name', $record['name'] ?? '')) ?>" placeholder="e.g. College of Health Sciences – Continuing Students" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                    <select name="academic_year_id" class="form-select" required>
                        <?= options(array_column($academicYears, 'name', 'id'), old('academic_year_id', $record['academic_year_id'] ?? ''), 'Select Academic Year') ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Bill Applies To <span class="text-danger">*</span></label>
                    <select name="scope" class="form-select" data-scope-select required>
                        <?= options([
                            'institution' => 'All Colleges',
                            'faculty'     => 'A College',
                            'department'  => 'A Department',
                            'program'     => 'A Programme',
                        ], $scope) ?>
                    </select>
                </div>
                <div class="col-md-8" data-scope="faculty">
                    <label class="form-label">College</label>
                    <select name="faculty_id" class="form-select">
                        <?= options(array_column($faculties, 'label', 'id'), old('faculty_id', $record['faculty_id'] ?? ''), 'Select College') ?>
                    </select>
                </div>
                <div class="col-md-8" data-scope="department">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <?= options(array_column($departments, 'label', 'id'), old('department_id', $record['department_id'] ?? ''), 'Select Department') ?>
                    </select>
                </div>
                <div class="col-md-8" data-scope="program">
                    <label class="form-label">Programme</label>
                    <select name="program_id" class="form-select">
                        <?= options(array_column($programs, 'label', 'id'), old('program_id', $record['program_id'] ?? ''), 'Select Programme') ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Student Status <span class="text-danger">*</span></label>
                    <select name="student_status" class="form-select" required>
                        <?= options(\App\Models\FeeStructure::STATUSES, old('student_status', $record['student_status'] ?? 'all')) ?>
                    </select>
                    <div class="form-text">New = first semester (Freshman I). 1st/2nd semester = the student's term in their class year (e.g. Sophomore I / II).</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tuition <span class="text-danger">*</span></label>
                    <select name="tuition_basis" class="form-select" required>
                        <?= options(['per_credit' => 'Per credit hour of registered courses', 'flat' => 'Flat amount (a Tuition item below)'], old('tuition_basis', $record['tuition_basis'] ?? 'per_credit')) ?>
                    </select>
                    <div class="form-text">Per-credit rates are set under Finance › Tuition Rates.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Year of Study</label>
                    <select name="year_of_study" class="form-select">
                        <?= options([1=>'Year 1', 2=>'Year 2', 3=>'Year 3', 4=>'Year 4', 5=>'Year 5', 6=>'Year 6'], old('year_of_study', $record['year_of_study'] ?? ''), 'Any Year') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Semester</label>
                    <select name="semester_number" class="form-select">
                        <?= options([1=>'Semester 1', 2=>'Semester 2', 3=>'Semester 3'], old('semester_number', $record['semester_number'] ?? ''), 'Any Semester') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Study Mode</label>
                    <select name="study_mode" class="form-select">
                        <?= enum_options(['full_time','part_time','evening','distance'], old('study_mode', $record['study_mode'] ?? ''), 'Any Mode') ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <?= enum_options(['draft','active','archived'], old('status', $record['status'] ?? 'draft')) ?>
                    </select>
                    <div class="form-text">Only active structures are used for billing.</div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Fee Structure' : 'Save Changes' ?></button>
            <a href="<?= url('/finance/fee-structures') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var select = document.querySelector('[data-scope-select]');
    function sync() {
        document.querySelectorAll('[data-scope]').forEach(function (el) {
            el.hidden = el.getAttribute('data-scope') !== select.value;
        });
    }
    select.addEventListener('change', sync);
    sync();
})();
</script>
<?php endsection(); ?>
