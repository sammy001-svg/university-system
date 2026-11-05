<?php
use App\Services\Elearning\ClassroomService;
use App\Services\Elearning\ExamService;

layout('layouts.app');
section('content');
$o       = $offering;
$editing = $editing ?? null;
$form    = $editing ?? ['title' => '', 'description' => '', 'platform' => 'jitsi', 'meeting_url' => '', 'meeting_id' => '',
                        'passcode' => '', 'starts_at' => date('Y-m-d H:00:00', strtotime('+1 day')), 'duration_minutes' => 60, 'recording_url' => ''];
$byWeek  = [];
foreach ($materials as $m) {
    $byWeek[$m['week'] === null ? 'General' : 'Week ' . $m['week']][] = $m;
}
$upcoming = array_filter($sessions, static fn (array $s): bool => in_array(ClassroomService::phase($s), ['upcoming', 'live'], true));
?>
<div class="page-head">
    <div>
        <h1><?= e($o['code']) ?> &middot; Online classroom</h1>
        <p class="lede"><?= e($o['title']) ?> &middot; Section <?= e($o['section']) ?> &middot; <?= e($o['semester_name'] . ' ' . $o['academic_year']) ?>
            &middot; <?= $students ?> student<?= $students === 1 ? '' : 's' ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $o['id']) ?>">&larr; Class list</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/teaching/' . $o['id'] . '/marks') ?>"><?= icon('edit', 'ico-sm') ?> Marks</a>
        <a class="btn btn-sm btn-primary btn-icon" href="<?= url('/teaching/' . $o['id'] . '/online-exams/create') ?>"><?= icon('plus', 'ico-sm') ?> New online exam</a>
    </div>
</div>

<div class="row g-3">
    <!-- ============ Online classes ============ -->
    <div class="col-xl-8">
        <div class="card mb-3" id="classes">
            <div class="card-header">
                <?= icon('video') ?> Online classes
                <span class="badge text-bg-light"><?= count($sessions) ?></span>
                <span class="ms-auto small fw-normal text-muted"><?= count($upcoming) ?> upcoming</span>
            </div>
            <div class="card-body py-1">
                <?php if ($sessions === []): ?>
                    <div class="empty-state"><?= icon('video') ?><p>No online classes yet. Schedule one below; the class list is notified.</p></div>
                <?php endif; ?>
                <?php foreach (array_reverse($sessions) as $s): $phase = ClassroomService::phase($s); $t = strtotime($s['starts_at']); ?>
                    <div class="el-item">
                        <div class="el-when <?= $phase === 'live' ? 'live' : '' ?>"><b><?= date('j', $t) ?></b><span><?= date('M', $t) ?></span></div>
                        <div class="el-item-main">
                            <div class="el-item-title">
                                <?= e($s['title']) ?>
                                <?php if ($phase === 'live'): ?><span class="live-badge ms-1">Live</span>
                                <?php elseif ($phase === 'cancelled'): ?><span class="badge text-bg-secondary">Cancelled</span>
                                <?php elseif ($phase === 'ended'): ?><span class="badge text-bg-light">Ended</span><?php endif; ?>
                            </div>
                            <div class="el-item-meta">
                                <?= date('D j M Y, H:i', $t) ?>&ndash;<?= date('H:i', strtotime(ClassroomService::endsAt($s))) ?>
                                &middot; <?= e(explode(' (', ClassroomService::PLATFORMS[$s['platform']])[0]) ?>
                                &middot; <?= (int) $s['attendees'] ?> joined
                                <?php if ($s['recording_url']): ?>&middot; <a href="<?= e($s['recording_url']) ?>" target="_blank" rel="noopener">Recording</a><?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex gap-1 flex-wrap justify-content-end">
                            <?php if ($phase !== 'cancelled' && $phase !== 'ended'): ?>
                                <a class="btn btn-sm btn-primary" href="<?= e($s['meeting_url']) ?>" target="_blank" rel="noopener"><?= icon('play', 'ico-sm') ?> Start</a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-light" href="<?= url('/teaching/class-sessions/' . $s['id'] . '/attendance') ?>" title="Who joined"><?= icon('users', 'ico-sm') ?></a>
                            <a class="btn btn-sm btn-light" href="<?= url('/teaching/' . $o['id'] . '/classroom?edit=' . $s['id']) ?>#session-form" title="Edit"><?= icon('edit', 'ico-sm') ?></a>
                            <?php if ($phase === 'upcoming' || $phase === 'live'): ?>
                                <form method="post" action="<?= url('/teaching/class-sessions/' . $s['id']) ?>" data-confirm="Cancel this class and notify the students?">
                                    <?= csrf_field() ?><?= method_field('PUT') ?><input type="hidden" name="action" value="cancel">
                                    <button class="btn btn-sm btn-light" title="Cancel class"><?= icon('logout', 'ico-sm') ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= url('/teaching/class-sessions/' . $s['id']) ?>" data-confirm="Delete this class and its attendance record?">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-light text-danger" title="Delete"><?= icon('trash', 'ico-sm') ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mb-3" id="session-form">
            <div class="card-header"><?= icon($editing ? 'edit' : 'plus') ?> <?= $editing ? 'Edit online class' : 'Schedule an online class' ?>
                <?php if ($editing): ?><a class="ms-auto small fw-normal" href="<?= url('/teaching/' . $o['id'] . '/classroom') ?>">Cancel editing</a><?php endif; ?>
            </div>
            <div class="card-body">
                <form method="post" action="<?= url($editing ? '/teaching/class-sessions/' . $editing['id'] : '/teaching/' . $o['id'] . '/class-sessions') ?>">
                    <?= csrf_field() ?>
                    <?php if ($editing): ?><?= method_field('PUT') ?><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Topic <span class="req">*</span></label>
                            <input class="form-control" name="title" required maxlength="150" value="<?= e($form['title']) ?>" placeholder="e.g. Week 5: Cell structure and function">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Platform</label>
                            <select class="form-select" name="platform" data-platform>
                                <?= options(ClassroomService::PLATFORMS, $form['platform']) ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Date and time <span class="req">*</span></label>
                            <input class="form-control" type="datetime-local" name="starts_at" required value="<?= e(date('Y-m-d\TH:i', strtotime($form['starts_at']))) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input class="form-control" type="number" name="duration_minutes" min="10" max="600" step="5" value="<?= (int) $form['duration_minutes'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Passcode</label>
                            <input class="form-control" name="passcode" maxlength="100" value="<?= e((string) $form['passcode']) ?>" placeholder="If the meeting has one">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Meeting link</label>
                            <input class="form-control" type="url" name="meeting_url" maxlength="500" value="<?= e((string) $form['meeting_url']) ?>" placeholder="https://...">
                            <div class="form-text" data-platform-hint>For Jitsi Meet, leave this blank and a private room is created for you. For Zoom, Meet or Teams, paste the invitation link.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Meeting ID</label>
                            <input class="form-control" name="meeting_id" maxlength="100" value="<?= e((string) $form['meeting_id']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes for students</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="What to prepare or read beforehand"><?= e((string) $form['description']) ?></textarea>
                        </div>
                        <?php if ($editing): ?>
                            <div class="col-12">
                                <label class="form-label">Recording link</label>
                                <input class="form-control" type="url" name="recording_url" maxlength="500" value="<?= e((string) $form['recording_url']) ?>" placeholder="https://... (shown to students after the class)">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-primary"><?= $editing ? 'Save changes' : 'Schedule class' ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <!-- ============ Online exams ============ -->
        <div class="card mb-3" id="exams">
            <div class="card-header"><?= icon('exam') ?> Online exams
                <a class="ms-auto small fw-normal" href="<?= url('/teaching/' . $o['id'] . '/online-exams/create') ?>">New</a>
            </div>
            <div class="card-body py-1">
                <?php if ($exams === []): ?>
                    <div class="empty-state py-4"><?= icon('exam') ?><p>No online exams yet.</p></div>
                <?php endif; ?>
                <?php foreach ($exams as $x): $phase = ExamService::phase($x); ?>
                    <a class="el-item text-decoration-none" href="<?= url('/teaching/online-exams/' . $x['id']) ?>">
                        <div class="el-item-main">
                            <div class="el-item-title"><?= e($x['title']) ?></div>
                            <div class="el-item-meta"><?= e(ExamService::TYPES[$x['exam_type']]) ?> &middot; <?= fdatetime($x['opens_at']) ?> &middot; <?= (int) $x['attempts'] ?> sat</div>
                        </div>
                        <?= status_badge(['draft' => 'draft', 'upcoming' => 'planned', 'open' => 'open', 'closed' => 'closed'][$phase]) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ============ Materials ============ -->
        <div class="card mb-3" id="materials">
            <div class="card-header"><?= icon('book') ?> Course materials <span class="badge text-bg-light"><?= count($materials) ?></span></div>
            <div class="card-body">
                <?php if ($materials === []): ?>
                    <p class="text-muted-sm">Share lecture notes, slides and readings. Students see them on their course page.</p>
                <?php endif; ?>
                <?php foreach ($byWeek as $week => $items): ?>
                    <div class="week-label"><?= e($week) ?></div>
                    <?php foreach ($items as $m): ?>
                        <div class="material">
                            <span class="material-ico"><?= icon($m['kind'] === 'link' ? 'link' : 'file', 'ico-sm') ?></span>
                            <div class="flex-grow-1 min-w-0">
                                <a class="fw-semibold d-block text-truncate" target="_blank" rel="noopener"
                                   href="<?= e($m['kind'] === 'link' ? $m['url'] : url($m['file_path'])) ?>"><?= e($m['title']) ?></a>
                                <span class="text-muted-sm"><?= $m['kind'] === 'link' ? 'Link' : e(strtoupper(pathinfo((string) $m['file_name'], PATHINFO_EXTENSION))) . ' &middot; ' . number_format((int) $m['file_size'] / 1024, 0) . ' KB' ?>
                                    <?= (int) $m['is_published'] === 0 ? ' &middot; hidden' : '' ?></span>
                            </div>
                            <form method="post" action="<?= url('/teaching/materials/' . $m['id']) ?>" data-confirm="Remove this material?">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-light text-danger" title="Remove"><?= icon('trash', 'ico-sm') ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <hr>
                <form method="post" action="<?= url('/teaching/' . $o['id'] . '/materials') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <input class="form-control form-control-sm" name="title" required maxlength="150" placeholder="Title, e.g. Lecture 3 slides">
                    </div>
                    <div class="d-flex gap-2 mb-2">
                        <select class="form-select form-select-sm" name="kind" data-material-kind style="max-width:120px">
                            <option value="file">File</option><option value="link">Link</option>
                        </select>
                        <input class="form-control form-control-sm" type="number" name="week" min="1" max="30" placeholder="Week">
                    </div>
                    <div class="mb-2" data-kind="file">
                        <input class="form-control form-control-sm" type="file" name="file">
                        <div class="form-text">PDF, Word, PowerPoint, Excel, images or ZIP, up to 25 MB.</div>
                    </div>
                    <div class="mb-2 d-none" data-kind="link">
                        <input class="form-control form-control-sm" type="url" name="url" placeholder="https://...">
                    </div>
                    <textarea class="form-control form-control-sm mb-2" name="description" rows="2" placeholder="Short description (optional)"></textarea>
                    <button class="btn btn-sm btn-primary btn-icon"><?= icon('upload', 'ico-sm') ?> Share with class</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
document.querySelectorAll('[data-material-kind]').forEach(function (select) {
  var sync = function () {
    var form = select.closest('form');
    form.querySelector('[data-kind="file"]').classList.toggle('d-none', select.value !== 'file');
    form.querySelector('[data-kind="link"]').classList.toggle('d-none', select.value !== 'link');
  };
  select.addEventListener('change', sync); sync();
});
</script>
<?php endsection(); ?>
