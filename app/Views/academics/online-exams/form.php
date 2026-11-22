<?php
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');

$isNew   = !isset($exam['id']);
$val     = static fn (string $k) => old_raw($k, $exam[$k] ?? '');
$dt      = static fn (string $k): string => date('Y-m-d\TH:i', strtotime((string) $val($k)) ?: time());
$check   = static fn (string $k): string => (string) old_raw($k, (string) ($exam[$k] ?? '0')) === '1' ? 'checked' : '';
$backUrl = $isNew
    ? ($selectedOfferingId ? '/academics/online-learning/' . $selectedOfferingId : '/academics/online-exams')
    : '/academics/online-exams/' . $exam['id'];
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= url('/academics/online-exams') ?>">Online Examinations</a> / <?= $isNew ? 'Schedule Exam' : e($offering['code']) ?></div>
        <h1><?= $isNew ? 'Schedule Online Exam' : 'Edit Online Exam Schedule' ?></h1>
        <p class="lede">
            <?php if (!$isNew): ?>
                <code><?= e($offering['code']) ?></code> Sec <?= e($offering['section']) ?> &middot; <?= e($offering['title']) ?>
                &middot; <?= e($offering['semester_name']) ?>
            <?php else: ?>
                Schedule an online quiz, test or examination window for a class offering. Newly scheduled exams start as Draft until questions are added.
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url($backUrl) ?>">&larr; Back</a>
</div>

<form method="post" action="<?= url($isNew ? '/academics/online-exams' : '/academics/online-exams/' . $exam['id']) ?>">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><?= icon('exam') ?> Exam Schedule &amp; Details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if ($isNew): ?>
                            <div class="col-12">
                                <label class="form-label">Class Offering <span class="req">*</span></label>
                                <?php partial('academics._offering_picker', ['offerings' => $offerings, 'selectedOfferingId' => $selectedOfferingId]); ?>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-8">
                            <label class="form-label">Exam Title <span class="req">*</span></label>
                            <input class="form-control" name="title" required maxlength="150" value="<?= e((string) $val('title')) ?>"
                                   placeholder="e.g. Mid-term Examination or Quiz 2">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Exam Type <span class="req">*</span></label>
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
                            <label class="form-label">Time Allowed (minutes) <span class="req">*</span></label>
                            <?php if (!empty($lockedDuration)): ?>
                                <input class="form-control" type="number" value="<?= (int) $val('duration_minutes') ?>" disabled>
                                <input type="hidden" name="duration_minutes" value="<?= (int) $exam['duration_minutes'] ?>">
                                <div class="form-text">Locked because students have started this exam.</div>
                            <?php else: ?>
                                <input class="form-control" type="number" name="duration_minutes" min="1" max="600" required value="<?= (int) $val('duration_minutes') ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <div class="form-text mt-0">Students may start at any time between opening and closing. Each candidate gets the time allowed, capped at the closing time.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Instructions to Candidates</label>
                            <textarea class="form-control" name="instructions" rows="4"
                                      placeholder="e.g. Answer ALL questions. Calculators are permitted."><?= e((string) $val('instructions')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?= icon('settings') ?> Rules &amp; Policies</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Pass Mark (%) <span class="req">*</span></label>
                        <input class="form-control" type="number" name="pass_percent" min="0" max="100" required value="<?= (int) $val('pass_percent') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Students See Results <span class="req">*</span></label>
                        <select class="form-select" name="show_results"><?= options(ExamService::RESULT_POLICIES, $val('show_results')) ?></select>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="shuffle_questions" value="1" id="sq" <?= $check('shuffle_questions') ?>>
                        <label class="form-check-label" for="sq">Shuffle question order for each student</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="shuffle_options" value="1" id="so" <?= $check('shuffle_options') ?>>
                        <label class="form-check-label" for="so">Shuffle answer choices</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="requires_enrollment" value="1" id="re" <?= $check('requires_enrollment') ?>>
                        <label class="form-check-label" for="re">Require complete registration (1st installment paid)</label>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-3"><?= $isNew ? 'Schedule Exam (Draft)' : 'Save Changes' ?></button>
            <a class="btn btn-light w-100 mt-2" href="<?= url($backUrl) ?>">Cancel</a>
        </div>
    </div>
</form>
<?php endsection(); ?>

