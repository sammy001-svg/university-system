<?php
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');
$isNew = !isset($exam['id']);
$val   = static fn (string $key) => old_raw($key, $exam[$key] ?? '');
$dt    = static fn (string $key): string => date('Y-m-d\TH:i', strtotime((string) old_raw($key, $exam[$key])) ?: time());
$check = static fn (string $key): string => (string) old_raw($key, (string) ($exam[$key] ?? '0')) === '1' ? 'checked' : '';
?>
<div class="page-head">
    <div>
        <h1><?= $isNew ? 'New online exam' : 'Exam settings' ?></h1>
        <p class="lede"><?= e($offering['code'] . ' - ' . $offering['title']) ?> &middot; Section <?= e($offering['section']) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url($isNew ? '/teaching/' . $offering['id'] . '/classroom#exams' : '/teaching/online-exams/' . $exam['id']) ?>">&larr; Back</a>
</div>

<form method="post" action="<?= url($isNew ? '/teaching/' . $offering['id'] . '/online-exams' : '/teaching/online-exams/' . $exam['id']) ?>">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><?= icon('exam') ?> The exam</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Title <span class="req">*</span></label>
                            <input class="form-control" name="title" required maxlength="150" value="<?= e((string) $val('title')) ?>" placeholder="e.g. Quiz 2: Chapters 3-4">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="exam_type"><?= options(ExamService::TYPES, $val('exam_type')) ?></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Opens <span class="req">*</span></label>
                            <input class="form-control" type="datetime-local" name="opens_at" required value="<?= e($dt('opens_at')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Closes <span class="req">*</span></label>
                            <input class="form-control" type="datetime-local" name="closes_at" required value="<?= e($dt('closes_at')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Time allowed (minutes) <span class="req">*</span></label>
                            <input class="form-control" type="number" name="duration_minutes" min="1" max="600" required value="<?= (int) $val('duration_minutes') ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-text mt-0">Students can start at any time between opening and closing. Each student gets the full time allowed,
                                but every attempt ends at the closing time at the latest.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Instructions to candidates</label>
                            <textarea class="form-control" name="instructions" rows="5" placeholder="e.g. Answer ALL questions. Calculators are permitted. The exam is closed-book."><?= e((string) $val('instructions')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?= icon('settings') ?> Rules</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Pass mark (%)</label>
                        <input class="form-control" type="number" name="pass_percent" min="0" max="100" value="<?= (int) $val('pass_percent') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Students see their results</label>
                        <select class="form-select" name="show_results"><?= options(ExamService::RESULT_POLICIES, $val('show_results')) ?></select>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="shuffle_questions" value="1" id="sq" <?= $check('shuffle_questions') ?>>
                        <label class="form-check-label" for="sq">Shuffle the order of questions for each student</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="shuffle_options" value="1" id="so" <?= $check('shuffle_options') ?>>
                        <label class="form-check-label" for="so">Shuffle the answer choices</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="requires_enrollment" value="1" id="re" <?= $check('requires_enrollment') ?>>
                        <label class="form-check-label" for="re">Only students whose registration is complete (1st installment paid)</label>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-3"><?= $isNew ? 'Create exam and add questions' : 'Save settings' ?></button>
        </div>
    </div>
</form>
<?php endsection(); ?>
