<?php
use App\Models\FeeStructure;

layout('layouts.app');
section('content');
$s        = $student;
$enrolled = ($enrollment['status'] ?? null) === 'enrolled';
$standing = FeeStructure::classStanding($s);
$hour     = (int) date('H');
?>
<!-- ============ Student header ============ -->
<section class="student-hero">
    <div class="student-avatar">
        <?php if (!empty($s['avatar'])): ?><img src="<?= e(uploaded($s['avatar'])) ?>" alt=""><?php else: ?><?= e(initials($s['first_name'], $s['last_name'])) ?><?php endif; ?>
    </div>
    <div>
        <div class="dash-eyebrow"><?= $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening') ?></div>
        <h1><?= e(trim($s['first_name'] . ' ' . ($s['other_name'] ?? '') . ' ' . $s['last_name'])) ?></h1>
        <div class="meta">
            <span>ID <b><?= e($s['admission_number']) ?></b></span>
            <span><?= e($s['program_name']) ?><?= !empty($s['minor_program_name']) ? ' &middot; minor ' . e($s['minor_program_name']) : '' ?></span>
            <span><b><?= e($standing) ?></b> &middot; Year <?= (int) $s['year_of_study'] ?></span>
            <span><?= e($s['faculty_name']) ?></span>
        </div>
    </div>
    <div class="hero-status">
        <?php if ($semester): ?>
            <div class="mb-2" style="color:var(--sidebar-text); font-size:12.5px"><?= e((string) ($semester['academic_year'] ?? '')) ?> &middot; <?= e($semester['name']) ?></div>
        <?php endif; ?>
        <?php if ($enrolled): ?>
            <span class="hero-pill ok"><?= icon('check', 'ico-sm') ?> Registration complete</span>
        <?php elseif ($enrollment !== null): ?>
            <span class="hero-pill warn"><?= icon('clock', 'ico-sm') ?> 1st installment due</span>
        <?php else: ?>
            <span class="hero-pill info">Not yet billed</span>
        <?php endif; ?>
    </div>
</section>

<?php if ($enrollment !== null && !$enrolled): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <?= icon('clock') ?>
        <div><strong>Your registration for <?= e($semester['name']) ?> is not complete.</strong>
            Pay the compulsory 1st installment (<?= e(money($enrollment['first_outstanding'])) ?> outstanding) to be enrolled, attend classes and sit examinations.
            <a class="fw-semibold" href="<?= url('/pay/' . $s['id'] . '/' . $enrollment['invoice_id']) ?>">Pay online now</a> &middot;
            <a href="<?= url('/portal/finance/invoice/' . $enrollment['invoice_id']) ?>">View my bill</a></div>
    </div>
<?php endif; ?>

<!-- ============ Key figures ============ -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <a class="kpi" href="<?= url('/portal/results') ?>">
            <div class="kpi-head"><span>Cumulative GPA</span><?= icon('chart') ?></div>
            <div class="kpi-value"><?= $cgpa !== null ? number_format($cgpa, 2) : '&ndash;' ?></div>
            <div class="kpi-sub"><?= $cgpa !== null ? e(classify_gpa($cgpa)) : 'No published results yet' ?></div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a class="kpi" href="<?= url('/portal/courses') ?>">
            <div class="kpi-head"><span>This semester</span><?= icon('book') ?></div>
            <div class="kpi-value"><?= count($registrations) ?> <small>courses</small></div>
            <div class="kpi-sub"><b><?= $credits ?></b> credit hours &middot; <b><?= $creditsEarned ?></b> earned in total</div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a class="kpi" href="<?= url('/portal/attendance') ?>">
            <div class="kpi-head"><span>Attendance</span><?= icon('check') ?></div>
            <div class="kpi-value"><?= e((string) $attendance) ?><small>%</small></div>
            <div class="meter <?= $attendance >= 75 ? 'ok' : ($attendance >= 50 ? 'warn' : 'bad') ?> mt-2"><span style="width:<?= min(100, (float) $attendance) ?>%"></span></div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a class="kpi" href="<?= url('/portal/finance') ?>">
            <div class="kpi-head"><span>Fee balance</span><?= icon('money') ?></div>
            <div class="kpi-value" style="color:<?= $balance > 0 ? 'var(--danger)' : 'var(--ok)' ?>"><?= e(money($balance)) ?></div>
            <div class="kpi-sub"><?= $balance > 0 ? 'Outstanding on your account' : 'Your account is up to date' ?></div>
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <!-- Online classes -->
        <div class="card mb-3">
            <div class="card-header"><?= icon('video') ?> Online classes this week
                <a class="ms-auto small fw-normal" href="<?= url('/portal/classes') ?>">All classes</a></div>
            <div class="card-body py-1">
                <?php if ($classes === []): ?>
                    <div class="empty-state py-4"><?= icon('video') ?><p>No online classes scheduled in the next 7 days.</p></div>
                <?php endif; ?>
                <?php foreach ($classes as $c): $t = strtotime($c['starts_at']); ?>
                    <div class="el-item">
                        <div class="el-when <?= $c['phase'] === 'live' ? 'live' : '' ?>"><b><?= date('j', $t) ?></b><span><?= date('M', $t) ?></span></div>
                        <div class="el-item-main">
                            <div class="el-item-title"><?= e($c['code']) ?> &middot; <?= e($c['title']) ?>
                                <?php if ($c['phase'] === 'live'): ?><span class="live-badge ms-1">Live</span><?php endif; ?></div>
                            <div class="el-item-meta"><?= date('l, H:i', $t) ?> &middot; <?= (int) $c['duration_minutes'] ?> min &middot; <?= e($c['course_title']) ?></div>
                        </div>
                        <?php if ($c['can_join']): ?>
                            <a class="btn btn-sm btn-primary" href="<?= url('/portal/classes/' . $c['id'] . '/join') ?>" target="_blank" rel="noopener"><?= icon('play', 'ico-sm') ?> Join</a>
                        <?php else: ?>
                            <span class="text-muted-sm text-nowrap">in <?= e(time_until($c['starts_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Courses -->
        <div class="card mb-3">
            <div class="card-header"><?= icon('book') ?> My courses
                <a class="ms-auto small fw-normal" href="<?= url('/portal/registration') ?>">Course registration</a></div>
            <?php if ($registrations === []): ?>
                <div class="card-body"><div class="empty-state"><?= icon('book') ?><p>No courses registered this semester.</p>
                    <a class="btn btn-sm btn-primary" href="<?= url('/portal/registration') ?>">Register courses</a></div></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Course</th><th>Lecturer</th><th class="text-center">Credits</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($registrations as $r): ?>
                            <tr>
                                <td><a class="fw-semibold" href="<?= url('/portal/courses/' . $r['offering_id']) ?>"><?= e($r['code']) ?></a>
                                    <div class="text-muted-sm"><?= e($r['title']) ?></div></td>
                                <td class="text-muted-sm"><?= e($r['lecturer_name'] ?? 'To be assigned') ?></td>
                                <td class="text-center"><?= (int) $r['credit_hours'] ?></td>
                                <td><?= status_badge($r['approval_status'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <?php if ($todo !== []): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('bell') ?> To do <span class="badge text-bg-warning"><?= count($todo) ?></span></div>
                <div class="card-body py-1">
                    <ul class="todo">
                        <?php foreach ($todo as $item): ?>
                            <li><a href="<?= url($item['url']) ?>"><span class="dot tone-<?= e($item['tone']) ?>"></span><span><?= e($item['label']) ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><?= icon('exam') ?> Online exams
                <a class="ms-auto small fw-normal" href="<?= url('/portal/exams') ?>">All exams</a></div>
            <div class="card-body py-1">
                <?php if ($exams === []): ?>
                    <p class="text-muted-sm py-3 mb-0">No exams open or coming up.</p>
                <?php endif; ?>
                <?php foreach ($exams as $x): ?>
                    <a class="el-item text-decoration-none" href="<?= url('/portal/exams/' . $x['id']) ?>">
                        <div class="el-item-main">
                            <div class="el-item-title"><?= e($x['code']) ?> &middot; <?= e($x['title']) ?></div>
                            <div class="el-item-meta"><?= $x['phase'] === 'open' ? 'Open until ' . fdatetime($x['closes_at']) : 'Opens ' . fdatetime($x['opens_at']) ?>
                                &middot; <?= (int) $x['duration_minutes'] ?> min</div>
                        </div>
                        <?= $x['phase'] === 'open' ? '<span class="badge text-bg-success">Open</span>' : '<span class="badge text-bg-light">Upcoming</span>' ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><?= icon('bell') ?> Announcements
                <a class="ms-auto small fw-normal" href="<?= url('/announcements') ?>">All</a></div>
            <div class="card-body py-2">
                <?php if ($announcements === []): ?>
                    <p class="text-muted-sm mb-0 py-2">No announcements.</p>
                <?php else: ?>
                    <ul class="list-clean">
                        <?php foreach ($announcements as $a): ?>
                            <li><a class="fw-semibold d-block" href="<?= url('/announcements/' . $a['id']) ?>"><?= e(str_limit($a['title'], 60)) ?></a>
                                <span class="text-muted-sm"><?= ago($a['published_at'] ?? $a['created_at']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><?= icon('check') ?> Quick links</div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="<?= url('/portal/course-guide') ?>"><?= icon('clipboard') ?><span>Course guide</span></a>
                    <a href="<?= url('/portal/timetable') ?>"><?= icon('clock') ?><span>Timetable</span></a>
                    <a href="<?= url('/portal/results') ?>"><?= icon('chart') ?><span>Results</span></a>
                    <a href="<?= url('/portal/records') ?>"><?= icon('clipboard') ?><span>Grade report</span></a>
                    <a href="<?= url('/portal/transcript') ?>"><?= icon('file') ?><span>Transcript</span></a>
                    <a href="<?= url('/portal/finance') ?>"><?= icon('money') ?><span>Fee statement</span></a>
                    <a href="<?= url('/portal/library') ?>"><?= icon('book') ?><span>My library</span></a>
                    <a href="<?= url('/portal/profile') ?>"><?= icon('user') ?><span>My profile</span></a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
