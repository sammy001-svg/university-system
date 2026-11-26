<?php
use App\Services\Elearning\ClassroomService;
use App\Services\Elearning\CourseAccess;
use App\Services\Elearning\ExamService;
use App\Services\Elearning\Oversight;

layout('layouts.app');
section('content');
$o = $offering;
$canDownload = CourseAccess::mayTeach((int) $o['id']) || can('elearning.manage');
$byWeek = [];
foreach ($materials as $m) {
    $byWeek[$m['week'] === null ? 'General' : 'Week ' . $m['week']][] = $m;
}
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= url('/academics/online-learning') ?>">Online Learning</a> / <?= e($o['code']) ?></div>
        <h1><?= e($o['code']) ?> &middot; <?= e($o['title']) ?></h1>
        <p class="lede">Section <?= e($o['section']) ?> &middot; <?= e($o['semester_name'] . ' ' . $o['academic_year']) ?>
            &middot; Lecturer: <?= $o['lecturer_name'] !== '' ? e($o['lecturer_name']) : 'Unassigned' ?>
            &middot; <?= e(humanize($o['delivery_mode'])) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-light" href="<?= url('/academics/online-learning') ?>">&larr; Online Learning</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/academics/offerings/' . $o['id'] . '/roster') ?>"><?= icon('users', 'ico-sm') ?> Class Roster</a>
        <?php if (can('elearning.manage')): ?>
            <a class="btn btn-sm btn-outline-primary btn-icon" href="<?= url('/academics/online-exams/create?offering=' . $o['id']) ?>"><?= icon('plus', 'ico-sm') ?> Schedule Exam</a>
            <a class="btn btn-sm btn-primary btn-icon" href="<?= url('/academics/online-learning/create?offering=' . $o['id']) ?>"><?= icon('plus', 'ico-sm') ?> Schedule Lesson</a>
        <?php endif; ?>
        <?php if ($canTeach): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('/teaching/' . $o['id'] . '/classroom') ?>"><?= icon('video', 'ico-sm') ?> Classroom</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= number_format($stats['held']) ?></b><span>Classes held</span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= number_format($stats['upcoming']) ?></b><span>Live or upcoming</span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= $stats['attendance'] === null ? '&ndash;' : $stats['attendance'] . '%' ?></b><span>Avg session attendance</span></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><b><?= number_format($stats['reached']) ?> / <?= number_format($registered) ?></b><span>Students joined &ge;1 class</span></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><?= icon('video') ?> Online Class Sessions <span class="badge text-bg-light ms-1"><?= count($sessions) ?></span></div>
            <div class="card-body py-1">
                <?php if ($sessions === []): ?>
                    <div class="empty-state py-4"><?= icon('video') ?><p class="mb-0">No online classes scheduled for this offering yet.</p></div>
                <?php endif; ?>
                <?php foreach ($sessions as $s):
                    $phase = ClassroomService::phase($s);
                    $t = strtotime((string) $s['starts_at']);
                    $pct = Oversight::pct($s['attendees'], $registered);
                ?>
                    <div class="el-item">
                        <div class="el-when <?= $phase === 'live' ? 'live' : '' ?>"><b><?= date('j', $t) ?></b><span><?= date('M', $t) ?></span></div>
                        <div class="el-item-main">
                            <div class="el-item-title">
                                <?= e($s['title']) ?>
                                <?php if ($phase === 'live'): ?><span class="live-badge ms-1">Live</span>
                                <?php elseif ($phase === 'upcoming'): ?><span class="badge text-bg-info ms-1">Upcoming</span>
                                <?php elseif ($phase === 'cancelled'): ?><span class="badge text-bg-secondary ms-1">Cancelled</span>
                                <?php else: ?><span class="badge text-bg-light ms-1">Held</span><?php endif; ?>
                            </div>
                            <div class="el-item-meta">
                                <?= date('D j M Y, H:i', $t) ?>&ndash;<?= date('H:i', strtotime(ClassroomService::endsAt($s))) ?>
                                &middot; <?= e(explode(' (', ClassroomService::PLATFORMS[$s['platform']] ?? $s['platform'])[0]) ?>
                                &middot; <?= (int) $s['attendees'] ?> / <?= $registered ?> joined<?= $pct !== null ? " ({$pct}%)" : '' ?>
                                <?php if (!empty($s['recording_url'])): ?>
                                    &middot; <a href="<?= e($s['recording_url']) ?>" target="_blank" rel="noopener">Recording</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (can('elearning.manage')): ?>
                            <div class="d-flex gap-1 flex-wrap justify-content-end">
                                <?php if (($phase === 'live' || $phase === 'upcoming') && !empty($s['meeting_url'])): ?>
                                    <a class="btn btn-sm btn-primary" href="<?= e($s['meeting_url']) ?>" target="_blank" rel="noopener"><?= icon('play', 'ico-sm') ?> Join</a>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-light" href="<?= url('/academics/online-learning/sessions/' . $s['id'] . '/edit') ?>" title="Edit"><?= icon('edit', 'ico-sm') ?></a>
                                <?php if ($phase === 'live' || $phase === 'upcoming'): ?>
                                    <form method="post" action="<?= url('/academics/online-learning/sessions/' . $s['id'] . '/status') ?>">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="complete">
                                        <button class="btn btn-sm btn-light" title="Mark as held"><?= icon('check', 'ico-sm') ?></button>
                                    </form>
                                    <form method="post" action="<?= url('/academics/online-learning/sessions/' . $s['id'] . '/status') ?>" data-confirm="Cancel this online lesson and notify students?">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                                        <button class="btn btn-sm btn-light" title="Cancel lesson"><?= icon('logout', 'ico-sm') ?></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= url('/academics/online-learning/sessions/' . $s['id']) ?>" data-confirm="Delete this online lesson and its attendance record?">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-light text-danger" title="Delete"><?= icon('trash', 'ico-sm') ?></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><?= icon('book') ?> Course Materials <span class="badge text-bg-light ms-1"><?= count($materials) ?></span></div>
            <div class="card-body">
                <?php if ($materials === []): ?>
                    <div class="empty-state py-4"><?= icon('file') ?><p class="mb-0">No lecture notes or links uploaded yet.</p></div>
                <?php endif; ?>
                <?php foreach ($byWeek as $weekLabel => $items): ?>
                    <div class="week-label"><?= e($weekLabel) ?></div>
                    <?php foreach ($items as $m): ?>
                        <div class="material">
                            <div class="material-ico"><?= icon($m['kind'] === 'link' ? 'link' : 'file') ?></div>
                            <div class="el-item-main">
                                <div class="fw-semibold">
                                    <?php if ($m['kind'] === 'link' && !empty($m['url'])): ?>
                                        <a href="<?= e($m['url']) ?>" target="_blank" rel="noopener"><?= e($m['title']) ?></a>
                                    <?php elseif ($m['kind'] === 'file' && $canDownload): ?>
                                        <a href="<?= url('/files/materials/' . $m['id']) ?>"><?= e($m['title']) ?></a>
                                    <?php else: ?>
                                        <?= e($m['title']) ?>
                                    <?php endif; ?>
                                    <?php if ((int) $m['is_published'] !== 1): ?><span class="badge text-bg-secondary ms-1">Hidden</span><?php endif; ?>
                                </div>
                                <div class="text-muted-sm">
                                    <?= $m['kind'] === 'link' ? 'External link' : e((string) ($m['file_name'] ?? 'File')) ?>
                                    &middot; Added <?= ago($m['created_at']) ?>
                                </div>
                            </div>
                            <?php if (can('elearning.manage')): ?>
                                <form method="post" action="<?= url('/academics/online-learning/materials/' . $m['id']) ?>"
                                      data-confirm="Remove &quot;<?= e($m['title']) ?>&quot; from the class?">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-light text-danger" title="Remove"><?= icon('trash', 'ico-sm') ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <?php if (can('elearning.manage')): ?>
                    <hr>
                    <h6 id="materials"><?= icon('upload') ?> Share notes with this class</h6>
                    <p class="text-muted-sm">A PDF, slide deck, spreadsheet or link. Published material appears on the
                        students' course page at once and they are notified. Up to <?= ClassroomService::MATERIAL_MAX_MB ?> MB.</p>
                    <form method="post" action="<?= url('/academics/online-learning/' . $o['id'] . '/materials') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="form-label">Title <span class="req">*</span></label>
                                <input class="form-control form-control-sm" name="title" maxlength="150" required
                                       value="<?= e((string) old_raw('title', '')) ?>" placeholder="e.g. Week 3 lecture notes">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Week</label>
                                <input class="form-control form-control-sm" type="number" name="week" min="1" max="30"
                                       value="<?= e((string) old_raw('week', '')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kind</label>
                                <select class="form-select form-select-sm" name="kind" data-material-kind>
                                    <?= options(['file' => 'Upload a file', 'link' => 'Link'], old_raw('kind', 'file')) ?>
                                </select>
                            </div>
                            <div class="col-12" data-material-for="file">
                                <label class="form-label">File <span class="req">*</span></label>
                                <input class="form-control form-control-sm" type="file" name="file"
                                       accept=".<?= implode(',.', ClassroomService::MATERIAL_TYPES) ?>">
                            </div>
                            <div class="col-12" data-material-for="link" style="display:none">
                                <label class="form-label">Link <span class="req">*</span></label>
                                <input class="form-control form-control-sm" type="url" name="url" placeholder="https://"
                                       value="<?= e((string) old_raw('url', '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control form-control-sm" name="description" rows="2"><?= e((string) old_raw('description', '')) ?></textarea>
                            </div>
                            <div class="col-12 d-flex align-items-center gap-3">
                                <label class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_published" value="1" checked>
                                    Publish to students now
                                </label>
                                <button class="btn btn-sm btn-primary ms-auto"><?= icon('upload', 'ico-sm') ?> Share with the class</button>
                            </div>
                        </div>
                    </form>
                    <script>
                    (function () {
                      var kind = document.querySelector('[data-material-kind]');
                      if (!kind) { return; }
                      var sync = function () {
                        document.querySelectorAll('[data-material-for]').forEach(function (el) {
                          el.style.display = el.getAttribute('data-material-for') === kind.value ? '' : 'none';
                        });
                      };
                      kind.addEventListener('change', sync);
                      sync();
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><?= icon('exam') ?> Online Examinations <span class="badge text-bg-light ms-1"><?= count($exams) ?></span></div>
            <div class="card-body py-1">
                <?php if ($exams === []): ?>
                    <div class="empty-state py-4"><?= icon('exam') ?><p class="mb-0">No online exams created for this offering yet.</p></div>
                <?php endif; ?>
                <?php foreach ($exams as $ex): $phase = ExamService::phase($ex); ?>
                    <div class="el-item">
                        <div class="el-item-main">
                            <div class="el-item-title">
                                <a href="<?= url('/academics/online-exams/' . $ex['id']) ?>"><?= e($ex['title']) ?></a>
                                <?= status_badge(['draft' => 'draft', 'upcoming' => 'planned', 'open' => 'open', 'closed' => 'closed'][$phase]) ?>
                            </div>
                            <div class="el-item-meta">
                                <?= e(ExamService::TYPES[$ex['exam_type']] ?? $ex['exam_type']) ?>
                                &middot; <?= (int) $ex['question_count'] ?> Qs &middot; <?= (int) $ex['duration_minutes'] ?> min
                                &middot; <?= (int) $ex['attempts'] ?> / <?= $registered ?> sat
                                <?= $ex['avg_pct'] !== null ? ' &middot; Avg ' . round((float) $ex['avg_pct'], 1) . '%' : '' ?>
                            </div>
                            <div class="text-muted-sm"><?= fdatetime($ex['opens_at']) ?> &ndash; <?= fdatetime($ex['closes_at']) ?></div>
                        </div>
                        <a class="btn btn-sm btn-light" href="<?= url('/academics/online-exams/' . $ex['id']) ?>">Inspect</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><?= icon('clipboard') ?> Online Assignments <span class="badge text-bg-light ms-1"><?= count($assignments) ?></span></div>
            <div class="card-body py-1">
                <?php if ($assignments === []): ?>
                    <div class="empty-state py-4"><?= icon('clipboard') ?><p class="mb-0">No assignments posted for this offering yet.</p></div>
                <?php endif; ?>
                <?php foreach ($assignments as $a): ?>
                    <div class="el-item">
                        <div class="el-item-main">
                            <div class="el-item-title">
                                <?= e($a['title']) ?>
                                <?= status_badge($a['status']) ?>
                            </div>
                            <div class="el-item-meta">
                                Due <?= fdatetime($a['due_at']) ?>
                                &middot; <?= (int) $a['submitted'] ?> / <?= $registered ?> submitted
                                &middot; Max <?= rtrim(rtrim(number_format((float) $a['max_score'], 2), '0'), '.') ?> marks
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>

