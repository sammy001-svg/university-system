<?php
use App\Models\Program;

layout('layouts.app');
section('content');
$years  = [1 => 'Freshman', 2 => 'Sophomore', 3 => 'Junior', 4 => 'Senior', 5 => 'Fifth year'];
$badge  = ['complete' => ['Passed', 'success'], 'in_progress' => ['In progress', 'info'], 'failed' => ['Retake', 'danger'], 'outstanding' => ['To take', 'light'], 'open' => ['To take', 'light']];
$types  = ['common' => 'General education', 'elective' => 'Elective', 'minor' => 'Minor', 'concentration' => 'Concentration', 'audit' => 'Audit (no credit)'];
$myYear = (int) $student['year_of_study'];
$mySem  = (int) $student['current_semester'];
$listTitles = ['elective' => 'Electives', 'concentration' => 'Concentration / content-area courses', 'minor' => 'Courses for students minoring in this programme'];
?>
<div class="page-head">
    <div>
        <h1>Course Guide</h1>
        <p class="lede">Your plan of study, semester by semester. Use it with your advisor when you register each semester.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('print', 'ico-sm') ?> Print</button>
        <a class="btn btn-sm btn-light" href="<?= url('/portal/degree-audit') ?>">Degree audit</a>
        <a class="btn btn-sm btn-primary" href="<?= url('/portal/registration') ?>">Register courses</a>
    </div>
</div>

<div class="course-head">
    <span class="course-code"><?= e($program['code']) ?> &middot; <?= e(humanize($program['level'])) ?></span>
    <h1><?= e($program['name']) ?></h1>
    <div class="text-muted-sm"><?= e($program['faculty_name']) ?> &middot; <?= e($program['department_name']) ?>
        &middot; <?= rtrim(rtrim((string) $program['duration_years'], '0'), '.') ?> years &middot; <b><?= (int) $totalCredits ?></b> credit hours to graduate
        &middot; minimum CGPA <?= number_format($minCgpa, 2) ?><?= $minor ? ' &middot; minor in ' . e($minor['program']['name']) : '' ?></div>
    <?php if ($audit): ?>
        <div class="d-flex align-items-center gap-3 mt-3">
            <div class="meter ok flex-fill" style="max-width:420px"><span style="width:<?= (int) $audit['percent'] ?>%"></span></div>
            <span class="small"><b><?= (int) $audit['earned'] ?></b> of <?= (int) $audit['required'] ?> credits earned (<?= (int) $audit['percent'] ?>%) &middot; you are a <b><?= e($standing) ?></b></span>
        </div>
    <?php endif; ?>
</div>

<?php if ($plan === []): ?>
    <div class="alert alert-warning">The course guide for this programme has not been published yet. Please ask your department.</div>
<?php endif; ?>

<?php foreach ($plan as $year => $semesters): ?>
    <div class="card mb-3">
        <div class="card-header"><?= icon('book') ?> Year <?= (int) $year ?> &middot; <?= e($years[$year] ?? '') ?></div>
        <div class="row g-0">
            <?php foreach ($semesters as $semester => $rows): $here = (int) $year === $myYear && (int) $semester === $mySem; ?>
                <div class="col-xl-6 <?= (int) $semester === 1 ? 'border-end' : '' ?>" <?= $here ? 'style="background:#fbf8fc"' : '' ?>>
                    <div class="px-3 pt-3 d-flex justify-content-between align-items-center">
                        <b>Semester <?= (int) $semester ?><?= $here ? ' <span class="badge text-bg-primary">You are here</span>' : '' ?></b>
                        <span class="text-muted-sm"><?= Program::termCredits($rows) ?> credit hours</span>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-sm align-middle mb-2">
                            <thead><tr><th class="ps-3">Code</th><th>Course</th><th class="text-center">Cr.</th><th class="pe-3 text-end">Status</th></tr></thead>
                            <tbody>
                            <?php $lastGroup = null; foreach ($rows as $row):
                                $item = $status[(int) $row['id']] ?? null;
                                $st   = $item['status'] ?? 'outstanding';
                                $alt  = $row['choice_group'] !== null && $row['choice_group'] === $lastGroup;
                                $lastGroup = $row['choice_group'];
                                $cid  = (int) ($row['course_id'] ?? 0); ?>
                                <tr>
                                    <td class="ps-3 text-nowrap"><?= $alt ? '<span class="text-muted-sm">or</span> ' : '' ?><?= $cid ? '<code>' . e($row['code']) . '</code>' : '' ?></td>
                                    <td>
                                        <?php if ($cid): ?>
                                            <?php if (isset($descriptions[$cid])): ?>
                                                <details><summary><?= e($row['title']) ?></summary><div class="text-muted-sm py-1"><?= e($descriptions[$cid]) ?></div></details>
                                            <?php else: ?><?= e($row['title']) ?><?php endif; ?>
                                        <?php else: ?>
                                            <i><?= e($row['slot_label'] ?: ucfirst($row['course_type']) . ' course') ?></i>
                                            <?php if (!empty($item['filled_by'])): ?><div class="text-muted-sm">you took <?= e($item['filled_by']) ?></div><?php endif; ?>
                                        <?php endif; ?>
                                        <div class="text-muted-sm">
                                            <?= isset($types[$row['course_type']]) ? e($types[$row['course_type']]) : '' ?>
                                            <?= isset($prerequisites[$cid]) ? ' &middot; needs ' . e(implode(', ', $prerequisites[$cid])) : '' ?>
                                            <?= $row['note'] ? ' &middot; ' . e($row['note']) : '' ?>
                                            <?= $cid && isset($offered[$cid]) && !in_array($st, ['complete', 'in_progress'], true) ? ' &middot; <span class="text-success">on offer this semester</span>' : '' ?>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= (int) $row['credit_hours'] ?></td>
                                    <td class="pe-3 text-end text-nowrap">
                                        <?php if ($item === null && $row['choice_group'] !== null): ?>
                                            <span class="text-muted-sm">alternative</span>
                                        <?php else: [$text, $tone] = $badge[$st]; ?>
                                            <span class="badge text-bg-<?= $tone ?>"><?= $text ?><?= !empty($item['result']['grade']) ? ' &middot; ' . e($item['result']['grade']) : '' ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="row g-3">
    <div class="col-xl-8">
        <?php foreach ($options as $type => $groups): if ($type === 'minor') { continue; } ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('clipboard') ?> <?= e($listTitles[$type] ?? ucfirst($type)) ?></div>
                <div class="card-body">
                    <?php foreach ($groups as $label => $rows): ?>
                        <?php if ($label !== ''): ?><div class="week-label"><?= e($label) ?></div><?php endif; ?>
                        <div class="row">
                            <?php foreach ($rows as $o): ?>
                                <div class="col-md-6 small py-1"><?= $o['course_id'] ? '<code>' . e($o['code']) . '</code> ' . e($o['title']) : '<i>' . e((string) $o['slot_label']) . '</i>' ?>
                                    <span class="text-muted">(<?= (int) $o['credit_hours'] ?> cr.)</span><?= $o['note'] ? '<div class="text-muted-sm">' . e($o['note']) . '</div>' : '' ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($minor): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('clipboard') ?> Your minor: <?= e($minor['program']['name']) ?></div>
                <div class="card-body">
                    <?php if ($minor['courses'] === []): ?><p class="text-muted-sm mb-0">The courses for this minor are set by the <?= e($minor['program']['department_name']) ?> department; ask your advisor.</p><?php endif; ?>
                    <?php foreach ($minor['courses'] as $label => $rows): ?>
                        <?php if ($label !== ''): ?><div class="week-label"><?= e($label) ?></div><?php endif; ?>
                        <div class="row">
                            <?php foreach ($rows as $o): ?>
                                <div class="col-md-6 small py-1"><?= $o['course_id'] ? '<code>' . e($o['code']) . '</code> ' . e($o['title']) : '<i>' . e((string) $o['slot_label']) . '</i>' ?>
                                    <span class="text-muted">(<?= (int) $o['credit_hours'] ?> cr.)</span></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-xl-4">
        <?php if ($grades !== []): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('chart') ?> Grading scale</div>
                <div class="table-wrap"><table class="table table-sm mb-0">
                    <thead><tr><th class="ps-3">Grade</th><th>Marks</th><th class="text-center">Points</th><th class="pe-3">Meaning</th></tr></thead>
                    <tbody>
                    <?php foreach ($grades as $g): ?>
                        <tr><td class="ps-3 fw-bold"><?= e($g['grade']) ?></td><td><?= rtrim(rtrim((string) $g['min_score'], '0'), '.') ?>&ndash;<?= rtrim(rtrim((string) $g['max_score'], '0'), '.') ?></td>
                            <td class="text-center"><?= number_format((float) $g['grade_point'], 2) ?></td>
                            <td class="pe-3 small <?= (int) $g['is_pass'] ? '' : 'text-danger' ?>"><?= e((string) $g['remarks']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header"><?= icon('check') ?> How to use this guide</div>
            <div class="card-body small">
                <ul class="mb-0 ps-3">
                    <li>Take the courses of each semester in order; a course marked "needs" has prerequisites.</li>
                    <li>"or" marks alternatives: take one of them.</li>
                    <li>Courses marked <span class="badge text-bg-danger">Retake</span> must be passed before you can graduate.</li>
                    <li>You need <?= (int) $totalCredits ?> credit hours and a CGPA of at least <?= number_format($minCgpa, 2) ?>.</li>
                    <li>Meet your <a href="<?= url('/portal/advising') ?>">academic advisor</a> before registering.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
