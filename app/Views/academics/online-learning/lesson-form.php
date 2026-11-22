<?php
use App\Services\Elearning\ClassroomService;

layout('layouts.app');
section('content');

$isNew = $session === null;
$val   = static fn (string $k, mixed $fallback = '') => old_raw($k, $form[$k] ?? $fallback);
$dt    = static fn (string $k): string => date('Y-m-d\TH:i', strtotime((string) $val($k)) ?: time());
$backUrl = $isNew
    ? ($selectedOfferingId ? '/academics/online-learning/' . $selectedOfferingId : '/academics/online-learning')
    : '/academics/online-learning/' . $offering['id'];
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= url('/academics/online-learning') ?>">Online Learning</a> / <?= $isNew ? 'Schedule Lesson' : e($offering['code']) ?></div>
        <h1><?= $isNew ? 'Schedule Online Lesson' : 'Edit Online Lesson' ?></h1>
        <p class="lede">
            <?php if (!$isNew): ?>
                <code><?= e($offering['code']) ?></code> Sec <?= e($offering['section']) ?> &middot; <?= e($offering['title']) ?>
                &middot; <?= e($offering['semester_name']) ?>
            <?php else: ?>
                Schedule a live online session for a class offering. Students on the class list and the assigned lecturer are notified automatically.
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url($backUrl) ?>">&larr; Back</a>
</div>

<div class="card">
    <div class="card-header"><?= icon($isNew ? 'plus' : 'edit') ?> <?= $isNew ? 'Lesson Details' : 'Update Lesson' ?></div>
    <div class="card-body">
        <form method="post" action="<?= url($isNew ? '/academics/online-learning' : '/academics/online-learning/sessions/' . $session['id']) ?>">
            <?= csrf_field() ?>
            <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
            <div class="row g-3">
                <?php if ($isNew): ?>
                    <div class="col-12">
                        <label class="form-label">Class Offering <span class="req">*</span></label>
                        <?php partial('academics._offering_picker', ['offerings' => $offerings, 'selectedOfferingId' => $selectedOfferingId]); ?>
                    </div>
                <?php endif; ?>
                <div class="col-md-8">
                    <label class="form-label">Lesson Topic <span class="req">*</span></label>
                    <input class="form-control" name="title" required maxlength="150" value="<?= e((string) $val('title')) ?>"
                           placeholder="e.g. Week 5: Cell structure and function">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Platform <span class="req">*</span></label>
                    <select class="form-select" name="platform" id="lesson_platform">
                        <?= options(ClassroomService::PLATFORMS, $val('platform', 'jitsi')) ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Date and Time <span class="req">*</span></label>
                    <input class="form-control" type="datetime-local" name="starts_at" required value="<?= e($dt('starts_at')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Duration (minutes) <span class="req">*</span></label>
                    <input class="form-control" type="number" name="duration_minutes" min="10" max="600" step="5" required value="<?= (int) $val('duration_minutes', 60) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Passcode</label>
                    <input class="form-control" name="passcode" maxlength="100" value="<?= e((string) $val('passcode')) ?>" placeholder="If the meeting has one">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Meeting Link <span class="req d-none" id="meeting_url_req">*</span></label>
                    <input class="form-control" type="url" name="meeting_url" id="meeting_url" maxlength="500"
                           value="<?= e((string) $val('meeting_url')) ?>" placeholder="https://...">
                    <div class="form-text" id="platform_hint">For Jitsi Meet, leave this blank and a private room is created automatically. For Zoom, Google Meet or Teams, paste the invitation link.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Meeting ID</label>
                    <input class="form-control" name="meeting_id" maxlength="100" value="<?= e((string) $val('meeting_id')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes for Students</label>
                    <textarea class="form-control" name="description" rows="3" placeholder="What to prepare or read beforehand"><?= e((string) $val('description')) ?></textarea>
                </div>
                <?php if (!$isNew): ?>
                    <div class="col-12">
                        <label class="form-label">Recording Link</label>
                        <input class="form-control" type="url" name="recording_url" maxlength="500"
                               value="<?= e((string) $val('recording_url')) ?>" placeholder="https://... (shown to students after the class)">
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><?= $isNew ? 'Schedule Lesson & Notify Class' : 'Save Changes' ?></button>
                <a class="btn btn-light" href="<?= url($backUrl) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
(function () {
    const plat = document.getElementById('lesson_platform');
    const url  = document.getElementById('meeting_url');
    const req  = document.getElementById('meeting_url_req');
    function sync() {
        const needsUrl = plat.value !== 'jitsi';
        url.required = needsUrl;
        req.classList.toggle('d-none', !needsUrl);
    }
    plat.addEventListener('change', sync);
    sync();
})();
</script>
<?php endsection(); ?>

