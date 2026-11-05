<?php
use App\Services\Elearning\ExamService;
use App\Services\Elearning\ClassroomService;

layout('layouts.app');
section('content');
$o = $offering;
$byWeek = [];
foreach ($materials as $m) {
    $byWeek[$m['week'] === null ? 'General' : 'Week ' . $m['week']][] = $m;
}
$attRate = (int) ($attendance['total'] ?? 0) > 0 ? round((int) $attendance['attended'] / (int) $attendance['total'] * 100) : null;
$joinable = array_filter($sessions, static fn (array $s): bool => $s['can_join']);
?>
<div class="course-head">
    <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>
            <span class="course-code"><?= e($o['code']) ?> &middot; <?= (int) $o['credit_hours'] ?> credit hours &middot; Section <?= e($o['section']) ?></span>
            <h1><?= e($o['title']) ?></h1>
            <div class="text-muted-sm">
                <?= icon('user', 'ico-sm') ?> <?= e($o['lecturer_name'] ?: 'Lecturer to be assigned') ?>
                <?php if ($o['lecturer_email']): ?>&middot; <a href="mailto:<?= e($o['lecturer_email']) ?>"><?= e($o['lecturer_email']) ?></a><?php endif; ?>
                &middot; <?= e($o['academic_year'] . ' ' . $o['semester_name']) ?> &middot; <?= $classmates ?> students &middot; <?= e(humanize($o['delivery_mode'])) ?> delivery
            </div>
        </div>
        <div class="d-flex align-items-start gap-2">
            <?php foreach ($joinable as $s): ?>
                <a class="btn btn-primary" href="<?= url('/portal/classes/' . $s['id'] . '/join') ?>" target="_blank" rel="noopener">
                    <?= icon('play', 'ico-sm') ?> Join <?= $s['phase'] === 'live' ? 'live class' : 'class' ?></a>
            <?php endforeach; ?>
            <a class="btn btn-light" href="<?= url('/portal/courses') ?>">&larr; My courses</a>
        </div>
    </div>
</div>

<nav class="el-tabs">
    <a href="#materials" class="active">Materials <span class="badge text-bg-light"><?= count($materials) ?></span></a>
    <a href="#classes">Online classes <span class="badge text-bg-light"><?= count($sessions) ?></span></a>
    <a href="#exams">Exams <span class="badge text-bg-light"><?= count($exams) ?></span></a>
    <a href="#progress">My progress</a>
</nav>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3" id="materials">
            <div class="card-header"><?= icon('book') ?> Course materials</div>
            <div class="card-body">
                <?php if ($materials === []): ?>
                    <div class="empty-state py-3"><?= icon('book') ?><p>Your lecturer has not shared any materials yet.</p></div>
                <?php endif; ?>
                <?php foreach ($byWeek as $week => $items): ?>
                    <div class="week-label"><?= e($week) ?></div>
                    <?php foreach ($items as $m): ?>
                        <div class="material">
                            <span class="material-ico"><?= icon($m['kind'] === 'link' ? 'link' : 'file', 'ico-sm') ?></span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($m['title']) ?></div>
                                <?php if ($m['description']): ?><div class="text-muted-sm"><?= e($m['description']) ?></div><?php endif; ?>
                            </div>
                            <a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="<?= e($m['kind'] === 'link' ? $m['url'] : url($m['file_path'])) ?>">
                                <?= $m['kind'] === 'link' ? 'Open' : icon('download', 'ico-sm') . ' ' . e(strtoupper(pathinfo((string) $m['file_name'], PATHINFO_EXTENSION))) ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mb-3" id="classes">
            <div class="card-header"><?= icon('video') ?> Online classes</div>
            <div class="card-body py-1">
                <?php if ($sessions === []): ?>
                    <div class="empty-state py-3"><?= icon('video') ?><p>No online classes scheduled for this course.</p></div>
                <?php endif; ?>
                <?php foreach (array_reverse($sessions) as $s): $t = strtotime($s['starts_at']); ?>
                    <div class="el-item">
                        <div class="el-when <?= $s['phase'] === 'live' ? 'live' : '' ?>"><b><?= date('j', $t) ?></b><span><?= date('M', $t) ?></span></div>
                        <div class="el-item-main">
                            <div class="el-item-title"><?= e($s['title']) ?>
                                <?php if ($s['phase'] === 'live'): ?><span class="live-badge ms-1">Live</span>
                                <?php elseif ($s['phase'] === 'cancelled'): ?><span class="badge text-bg-secondary">Cancelled</span><?php endif; ?></div>
                            <div class="el-item-meta"><?= date('D j M, H:i', $t) ?> &middot; <?= (int) $s['duration_minutes'] ?> min
                                <?php if ($s['phase'] === 'ended'): ?>&middot; <?= $s['joined_at'] ? 'you attended' : 'you did not join' ?><?php endif; ?>
                                <?php if ($s['passcode'] && $s['phase'] !== 'ended'): ?>&middot; passcode <code><?= e($s['passcode']) ?></code><?php endif; ?></div>
                            <?php if ($s['description']): ?><div class="text-muted-sm"><?= e($s['description']) ?></div><?php endif; ?>
                        </div>
                        <?php if ($s['can_join']): ?>
                            <a class="btn btn-sm btn-primary" href="<?= url('/portal/classes/' . $s['id'] . '/join') ?>" target="_blank" rel="noopener">Join</a>
                        <?php elseif ($s['phase'] === 'ended' && $s['recording_url']): ?>
                            <a class="btn btn-sm btn-light" href="<?= e($s['recording_url']) ?>" target="_blank" rel="noopener"><?= icon('play', 'ico-sm') ?> Recording</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3" id="exams">
            <div class="card-header"><?= icon('exam') ?> Online exams</div>
            <div class="card-body py-1">
                <?php if ($exams === []): ?>
                    <p class="text-muted-sm py-3 mb-0">No online exams for this course yet.</p>
                <?php endif; ?>
                <?php foreach ($exams as $x): $a = $x['attempt']; ?>
                    <a class="el-item text-decoration-none" href="<?= url('/portal/exams/' . $x['id']) ?>">
                        <div class="el-item-main">
                            <div class="el-item-title"><?= e($x['title']) ?></div>
                            <div class="el-item-meta"><?= e(ExamService::TYPES[$x['exam_type']]) ?> &middot;
                                <?= $x['phase'] === 'upcoming' ? 'opens ' . fdatetime($x['opens_at']) : ($x['phase'] === 'open' ? 'closes ' . fdatetime($x['closes_at']) : 'closed') ?></div>
                        </div>
                        <?php if ($a !== null && $a['status'] !== 'in_progress'): ?><span class="badge text-bg-success">Submitted</span>
                        <?php elseif ($a !== null): ?><span class="badge text-bg-warning">In progress</span>
                        <?php elseif ($x['phase'] === 'open'): ?><span class="badge text-bg-primary">Open</span>
                        <?php elseif ($x['phase'] === 'closed'): ?><span class="badge text-bg-light">Missed</span>
                        <?php else: ?><span class="badge text-bg-light">Upcoming</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" id="progress">
            <div class="card-header"><?= icon('chart') ?> My progress</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1"><span class="text-muted-sm">Class attendance</span>
                    <b><?= $attRate === null ? 'No register yet' : $attRate . '%' ?></b></div>
                <?php if ($attRate !== null): ?>
                    <div class="meter <?= $attRate >= 75 ? 'ok' : ($attRate >= 50 ? 'warn' : 'bad') ?> mb-3"><span style="width:<?= $attRate ?>%"></span></div>
                <?php endif; ?>
                <div class="dash-subhead mt-2">Coursework</div>
                <?php if ($assessments === []): ?>
                    <p class="text-muted-sm mb-0">No published coursework yet.</p>
                <?php endif; ?>
                <?php foreach ($assessments as $a): ?>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <div><div class="fw-semibold small"><?= e($a['title']) ?></div>
                            <div class="text-muted-sm"><?= e(humanize($a['type'])) ?> &middot; weight <?= rtrim(rtrim((string) $a['weight'], '0'), '.') ?>%</div></div>
                        <div class="text-end small fw-semibold"><?= $a['score'] !== null ? rtrim(rtrim((string) $a['score'], '0'), '.') . ' / ' . rtrim(rtrim((string) $a['max_score'], '0'), '.') : ($a['status'] === 'missed' ? 'Missed' : '&ndash;') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
