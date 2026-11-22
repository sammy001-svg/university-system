<?php
use App\Services\Elearning\ExamService;
use App\Services\Elearning\Oversight;

layout('layouts.app');
section('content');

$semesterOptions = ['0' => 'All semesters'] + $semesters;
$selectedSem = $filters['semester'] ?? '0';
?>
<div class="page-head">
    <div>
        <h1>Online Examinations</h1>
        <p class="lede">Institutional oversight of quizzes, tests and online exams across all classes: participation, marking backlog and integrity checks.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary btn-sm btn-icon" href="<?= url('/academics/online-learning') ?>">
            <?= icon('video', 'ico-sm') ?> Online Learning
        </a>
        <a class="btn btn-outline-secondary btn-sm btn-icon" href="<?= e(url('/academics/online-exams' . query_with(['semester' => $selectedSem, 'export' => 'csv', 'page' => null]))) ?>">
            <?= icon('download', 'ico-sm') ?> Export CSV
        </a>
        <?php if (can('elearning.manage')): ?>
            <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/academics/online-exams/create') ?>">
                <?= icon('plus', 'ico-sm') ?> Schedule Online Exam
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b class="<?= $kpis['open_now'] > 0 ? 'text-success' : '' ?>"><?= number_format($kpis['open_now']) ?></b>
            <span>Open now<?= $kpis['writing'] > 0 ? ' (' . $kpis['writing'] . ' writing)' : '' ?></span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= number_format($kpis['next7']) ?></b>
            <span>Opening next 7 days</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b class="<?= $kpis['to_mark'] > 0 ? 'text-warning' : '' ?>"><?= number_format($kpis['to_mark']) ?></b>
            <span>Scripts to mark</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= $kpis['participation'] === null ? '&ndash;' : $kpis['participation'] . '%' ?></b>
            <span>Participation</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b><?= $kpis['average'] === null ? '&ndash;' : $kpis['average'] . '%' ?></b>
            <span>Overall average</span>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="mini-stat">
            <b class="<?= $kpis['flagged'] > 0 ? 'text-danger' : '' ?>"><?= number_format($kpis['flagged']) ?></b>
            <span>Integrity flags</span>
        </div>
    </div>
</div>

<form class="filter-bar" method="get" action="<?= url('/academics/online-exams') ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><?= icon('search', 'ico-sm') ?></span>
                <input type="search" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Exam title, course code or name...">
            </div>
        </div>
        <div class="col-md-2">
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
            <label class="form-label">Status</label>
            <select name="phase" class="form-select form-select-sm" data-auto-submit>
                <?= options($phases, $filters['phase'], 'All statuses') ?>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">Type</label>
            <select name="type" class="form-select form-select-sm" data-auto-submit>
                <?= options(ExamService::TYPES, $filters['type'], 'All') ?>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">Attention</label>
            <select name="flag" class="form-select form-select-sm" data-auto-submit>
                <?= options(['marking' => 'Needs marking', 'integrity' => 'Flagged'], $filters['flag'], 'All') ?>
            </select>
        </div>
        <div class="col-md-1">
            <button class="btn btn-sm btn-primary w-100" type="submit">Apply</button>
        </div>
        <?php if ($filters['q'] !== '' || $filters['department'] !== null || $filters['phase'] !== '' || $filters['type'] !== '' || $filters['flag'] !== '' || $filters['semester'] === null): ?>
            <div class="col-auto">
                <a class="btn btn-sm btn-link" href="<?= url('/academics/online-exams') ?>">Reset</a>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php $rows = $result['data']; ?>
<div class="card">
    <div class="card-header">
        <?= icon('exam') ?> Online Exams Register
        <span class="badge text-bg-light ms-1"><?= number_format((int) $result['total']) ?></span>
        <?php if ((int) $result['total'] > 0): ?>
            <span class="ms-auto text-muted-sm fw-normal">Showing <?= (int) $result['from'] ?>&ndash;<?= (int) $result['to'] ?> of <?= number_format((int) $result['total']) ?></span>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Exam</th>
                <th>Class &amp; Lecturer</th>
                <th>Window</th>
                <th>Status</th>
                <th class="text-end">Sat</th>
                <th class="text-end">To mark</th>
                <th class="text-end">Avg</th>
                <th class="text-end">Flags</th>
                <th class="text-end"></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9"><div class="empty-state"><?= icon('exam') ?><p class="mb-0">No online examinations match these filters.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
                $phase = ExamService::phase($r);
                $pct = Oversight::pct($r['attempts'], $r['registered']);
            ?>
                <tr>
                    <td>
                        <a class="fw-semibold" href="<?= url('/academics/online-exams/' . $r['id']) ?>"><?= e($r['title']) ?></a>
                        <div class="text-muted-sm">
                            <?= e(ExamService::TYPES[$r['exam_type']] ?? $r['exam_type']) ?>
                            &middot; <?= (int) $r['question_count'] ?> Qs
                            &middot; <?= (int) $r['duration_minutes'] ?> min
                        </div>
                    </td>
                    <td>
                        <div><code><?= e($r['code']) ?></code> Sec <?= e($r['section']) ?> &middot; <?= e($r['course_title']) ?></div>
                        <div class="text-muted-sm">
                            <?= $r['lecturer_name'] !== '' ? e($r['lecturer_name']) : 'Unassigned' ?>
                            &middot; <?= e($r['semester_name']) ?>
                        </div>
                    </td>
                    <td class="text-muted-sm text-nowrap">
                        <div><?= fdatetime($r['opens_at']) ?></div>
                        <div><?= fdatetime($r['closes_at']) ?></div>
                    </td>
                    <td>
                        <?= status_badge($badges[$phase] ?? $phase) ?>
                        <?php if ((int) $r['writing'] > 0): ?>
                            <div class="text-muted-sm"><?= (int) $r['writing'] ?> writing</div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <span class="fw-semibold"><?= (int) $r['attempts'] ?></span> / <?= (int) $r['registered'] ?>
                        <?php if ($pct !== null): ?><div class="text-muted-sm"><?= $pct ?>%</div><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?= (int) $r['to_mark'] > 0 ? '<span class="badge text-bg-warning">' . (int) $r['to_mark'] . '</span>' : '<span class="text-muted">&ndash;</span>' ?>
                    </td>
                    <td class="text-end fw-semibold">
                        <?= $r['avg_pct'] !== null ? round((float) $r['avg_pct'], 1) . '%' : '<span class="text-muted fw-normal">&ndash;</span>' ?>
                    </td>
                    <td class="text-end">
                        <?= (int) $r['flagged'] > 0 ? '<span class="badge text-bg-danger">' . (int) $r['flagged'] . '</span>' : '<span class="text-muted">&ndash;</span>' ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= url('/academics/online-exams/' . $r['id']) ?>">Inspect</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ((int) $result['last_page'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center bg-white">
            <span class="text-muted-sm">Page <?= (int) $result['page'] ?> of <?= (int) $result['last_page'] ?></span>
            <?= paginate_links($result) ?>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
