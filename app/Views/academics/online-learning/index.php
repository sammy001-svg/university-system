<?php
use App\Services\Elearning\ClassroomService;
use App\Services\Elearning\Oversight;

layout('layouts.app');
section('content');

$semesterOptions = ['0' => 'All semesters'] + $semesters;
$selectedSem = $filters['semester'] ?? '0';
$baseQuery = static fn (array $extra = []): string => url('/academics/online-learning' . query_with(array_merge([
    'semester'   => $selectedSem,
    'department' => $filters['department'] ?? '',
    'q'          => $filters['q'] ?? '',
    'page'       => null,
], $extra)));
?>
<div class="page-head">
    <div>
        <h1>Online Learning</h1>
        <p class="lede">Live and scheduled online classes, student attendance, and e-learning adoption across course offerings.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary btn-sm btn-icon" href="<?= url('/academics/online-exams') ?>">
            <?= icon('exam', 'ico-sm') ?> Online Exams
        </a>
        <a class="btn btn-outline-secondary btn-sm btn-icon" href="<?= e(url('/academics/online-learning' . query_with(['semester' => $selectedSem, 'export' => 'csv', 'page' => null]))) ?>">
            <?= icon('download', 'ico-sm') ?> Export CSV
        </a>
        <?php if (can('elearning.manage')): ?>
            <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/academics/online-learning/create') ?>">
                <?= icon('plus', 'ico-sm') ?> Schedule Lesson
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b class="<?= $kpis['live'] > 0 ? 'text-danger' : '' ?>"><?= number_format($kpis['live']) ?></b>
            <span>Live now</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= number_format($kpis['next7']) ?></b>
            <span>Next 7 days</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= number_format($kpis['held']) ?></b>
            <span>Classes held<?= $kpis['cancelled'] > 0 ? ' (' . $kpis['cancelled'] . ' cancelled)' : '' ?></span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= $kpis['attendance'] === null ? '&ndash;' : $kpis['attendance'] . '%' ?></b>
            <span>Avg attendance</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= number_format($kpis['active']) ?> / <?= number_format($kpis['classes']) ?></b>
            <span>Active online classes</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= number_format($kpis['materials']) ?></b>
            <span>Published materials</span>
        </div>
    </div>
</div>

<div class="el-tabs">
    <a class="<?= $tab === 'sessions' ? 'active' : '' ?>" href="<?= e($baseQuery(['tab' => 'sessions', 'activity' => null])) ?>">
        Online Classes <span class="badge text-bg-light"><?= number_format($kpis['live'] + $kpis['next7'] + $kpis['held']) ?></span>
    </a>
    <a class="<?= $tab === 'coverage' ? 'active' : '' ?>" href="<?= e($baseQuery(['tab' => 'coverage', 'phase' => null])) ?>">
        Course Adoption <span class="badge text-bg-light"><?= number_format($kpis['classes']) ?></span>
    </a>
</div>

<form class="filter-bar" method="get" action="<?= url('/academics/online-learning') ?>">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><?= icon('search', 'ico-sm') ?></span>
                <input type="search" name="q" class="form-control" value="<?= e($filters['q']) ?>"
                       placeholder="<?= $tab === 'sessions' ? 'Course code, title or topic...' : 'Course code or title...' ?>">
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select form-select-sm" data-auto-submit>
                <?= options($semesterOptions, $selectedSem) ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Department</label>
            <select name="department" class="form-select form-select-sm" data-auto-submit>
                <?= options($departments, $filters['department'] ?? '', 'All departments') ?>
            </select>
        </div>
        <div class="col-md-2">
            <?php if ($tab === 'sessions'): ?>
                <label class="form-label">Status</label>
                <select name="phase" class="form-select form-select-sm" data-auto-submit>
                    <?= options($phases, $filters['phase'], 'All statuses') ?>
                </select>
            <?php else: ?>
                <label class="form-label">Adoption</label>
                <select name="activity" class="form-select form-select-sm" data-auto-submit>
                    <?= options(['active' => 'Active online', 'none' => 'No online activity'], $filters['activity'], 'All classes') ?>
                </select>
            <?php endif; ?>
        </div>
        <div class="col-md-1">
            <button class="btn btn-sm btn-primary w-100" type="submit">Apply</button>
        </div>
        <?php if ($filters['q'] !== '' || $filters['department'] !== null || $filters['phase'] !== '' || $filters['activity'] !== '' || $filters['semester'] === null): ?>
            <div class="col-auto">
                <a class="btn btn-sm btn-link" href="<?= url('/academics/online-learning?tab=' . e($tab)) ?>">Reset</a>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php $rows = $result['data']; ?>
<div class="card">
    <div class="card-header">
        <?= icon($tab === 'sessions' ? 'video' : 'book') ?>
        <?= $tab === 'sessions' ? 'Online Class Sessions' : 'Online Activity by Course Offering' ?>
        <span class="badge text-bg-light ms-1"><?= number_format((int) $result['total']) ?></span>
        <?php if ((int) $result['total'] > 0): ?>
            <span class="ms-auto text-muted-sm fw-normal">Showing <?= (int) $result['from'] ?>&ndash;<?= (int) $result['to'] ?> of <?= number_format((int) $result['total']) ?></span>
        <?php endif; ?>
    </div>

    <div class="table-wrap">
        <?php if ($tab === 'sessions'): ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>When</th>
                    <th>Class &amp; Topic</th>
                    <th>Lecturer</th>
                    <th>Platform</th>
                    <th>Status</th>
                    <th class="text-end">Attendance</th>
                    <th class="text-end"></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7"><div class="empty-state"><?= icon('video') ?><p class="mb-0">No online class sessions match these filters.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r):
                    $phase = ClassroomService::phase($r);
                    $pct = Oversight::pct($r['attendees'], $r['registered']);
                    $t = strtotime((string) $r['starts_at']);
                ?>
                    <tr>
                        <td class="text-nowrap">
                            <div class="fw-semibold"><?= date('D j M Y', $t) ?></div>
                            <div class="text-muted-sm"><?= date('H:i', $t) ?>&ndash;<?= date('H:i', strtotime(ClassroomService::endsAt($r))) ?> (<?= (int) $r['duration_minutes'] ?>m)</div>
                        </td>
                        <td>
                            <a class="fw-semibold" href="<?= url('/academics/online-learning/' . $r['offering_id']) ?>">
                                <code><?= e($r['code']) ?></code> Sec <?= e($r['section']) ?> &middot; <?= e($r['title']) ?>
                            </a>
                            <div class="text-muted-sm"><?= e($r['course_title']) ?><?= $r['department_name'] ? ' &middot; ' . e($r['department_name']) : '' ?></div>
                        </td>
                        <td><?= $r['lecturer_name'] !== '' ? e($r['lecturer_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                        <td class="small">
                            <?= e(explode(' (', ClassroomService::PLATFORMS[$r['platform']] ?? $r['platform'])[0]) ?>
                            <?php if (!empty($r['recording_url'])): ?>
                                <div class="text-muted-sm"><a href="<?= e($r['recording_url']) ?>" target="_blank" rel="noopener">Recording</a></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($phase === 'live'): ?>
                                <span class="live-badge">Live</span>
                            <?php elseif ($phase === 'upcoming'): ?>
                                <span class="badge text-bg-info">Upcoming</span>
                            <?php elseif ($phase === 'ended'): ?>
                                <span class="badge text-bg-success">Held</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <span class="fw-semibold"><?= (int) $r['attendees'] ?></span> / <?= (int) $r['registered'] ?>
                            <?php if ($pct !== null): ?><div class="text-muted-sm"><?= $pct ?>%</div><?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light" href="<?= url('/academics/online-learning/' . $r['offering_id']) ?>">Inspect</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Course Offering</th>
                    <th>Lecturer</th>
                    <th>Mode</th>
                    <th class="text-end">Registered</th>
                    <th class="text-end">Held</th>
                    <th class="text-end">Scheduled</th>
                    <th class="text-end">Materials</th>
                    <th class="text-end">Exams</th>
                    <th class="text-end">Assignments</th>
                    <th>Last Activity</th>
                    <th class="text-end"></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="11"><div class="empty-state"><?= icon('book') ?><p class="mb-0">No course offerings match these filters.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r):
                    $hasAny = ((int) $r['held'] + (int) $r['upcoming'] + (int) $r['materials'] + (int) $r['exams'] + (int) $r['assignments']) > 0;
                ?>
                    <tr>
                        <td>
                            <a class="fw-semibold" href="<?= url('/academics/online-learning/' . $r['id']) ?>">
                                <code><?= e($r['code']) ?></code> Sec <?= e($r['section']) ?> &middot; <?= e($r['course_title']) ?>
                            </a>
                            <?php if ($r['department_name']): ?><div class="text-muted-sm"><?= e($r['department_name']) ?></div><?php endif; ?>
                        </td>
                        <td><?= $r['lecturer_name'] !== '' ? e($r['lecturer_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                        <td><span class="badge text-bg-<?= $r['delivery_mode'] === 'online' ? 'primary' : ($r['delivery_mode'] === 'hybrid' ? 'info' : 'light') ?>"><?= e(humanize($r['delivery_mode'])) ?></span></td>
                        <td class="text-end"><?= (int) $r['registered'] ?></td>
                        <td class="text-end"><?= (int) $r['held'] ?: '<span class="text-muted">0</span>' ?></td>
                        <td class="text-end"><?= (int) $r['upcoming'] ? '<span class="badge text-bg-info">' . (int) $r['upcoming'] . '</span>' : '<span class="text-muted">0</span>' ?></td>
                        <td class="text-end"><?= (int) $r['materials'] ?: '<span class="text-muted">0</span>' ?></td>
                        <td class="text-end"><?= (int) $r['exams'] ?: '<span class="text-muted">0</span>' ?></td>
                        <td class="text-end"><?= (int) $r['assignments'] ?: '<span class="text-muted">0</span>' ?></td>
                        <td class="text-muted-sm text-nowrap">
                            <?= $r['last_activity'] ? ago($r['last_activity']) : ($hasAny ? 'Scheduled' : '<span class="badge text-bg-light">None yet</span>') ?>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-light" href="<?= url('/academics/online-learning/' . $r['id']) ?>">Inspect</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ((int) $result['last_page'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center bg-white">
            <span class="text-muted-sm">Page <?= (int) $result['page'] ?> of <?= (int) $result['last_page'] ?></span>
            <?= paginate_links($result) ?>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
