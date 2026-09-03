<?php
layout('layouts.app');
$name      = trim($student['first_name'] . ' ' . ($student['other_name'] ?? '') . ' ' . $student['last_name']);
$pageTitle = $name;
section('content');
$paidPercent = $billed > 0 ? min(100, ($paid / $billed) * 100) : 0;
?>
<div class="page-head">
    <div>
        <nav class="breadcrumb"><a href="<?= url('/students') ?>">&larr; Back to students</a></nav>
        <h1><?= e($name) ?></h1>
        <p class="lede"><?= e($student['admission_number']) ?> &middot; <?= e($student['program_name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm btn-icon" href="<?= url('/students/' . $student['id'] . '/transcript') ?>">
            <?= icon('file', 'ico-sm') ?> Transcript
        </a>
        <a class="btn btn-outline-secondary btn-sm btn-icon" href="<?= url('/students/' . $student['id'] . '/statement') ?>">
            <?= icon('money', 'ico-sm') ?> Fee statement
        </a>
        <?php if (can('students.edit')): ?>
            <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/students/' . $student['id'] . '/edit') ?>">
                <?= icon('edit', 'ico-sm') ?> Edit
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <span class="avatar avatar-lg mx-auto mb-2" style="background:<?= e(avatar_color($name)) ?>">
                    <?php if (!empty($student['avatar'])): ?>
                        <img src="<?= uploaded($student['avatar']) ?>" alt="">
                    <?php else: ?><?= e(initials($student['first_name'], $student['last_name'])) ?><?php endif; ?>
                </span>
                <h5 class="mb-0"><?= e($name) ?></h5>
                <div class="text-muted-sm mb-2"><?= e($student['email']) ?></div>
                <?= status_badge($student['status']) ?>

                <div class="divider"></div>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="fw-semibold fs-5"><?= number_format((float) $student['cgpa'], 2) ?></div>
                        <div class="text-muted-sm">CGPA</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-semibold fs-5"><?= (int) $student['credits_earned'] ?></div>
                        <div class="text-muted-sm">Credits</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-semibold fs-5"><?= number_format($attendance, 0) ?>%</div>
                        <div class="text-muted-sm">Attendance</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><?= icon('clipboard') ?> Academic details</div>
            <div class="card-body">
                <ul class="list-clean text-muted-sm">
                    <li><strong>Registration no</strong><div><?= e($student['registration_number'] ?? '-') ?></div></li>
                    <li><strong>Programme</strong><div><?= e($student['program_name']) ?></div></li>
                    <li><strong>Department</strong><div><?= e($student['department_name']) ?></div></li>
                    <li><strong>Faculty</strong><div><?= e($student['faculty_name']) ?></div></li>
                    <li><strong>Year / semester</strong><div>Year <?= (int) $student['year_of_study'] ?>, Semester <?= (int) $student['current_semester'] ?></div></li>
                    <li><strong>Study mode</strong><div><?= e(humanize($student['study_mode'])) ?></div></li>
                    <li><strong>Admitted</strong><div><?= fdate($student['admission_date']) ?></div></li>
                    <li><strong>Sponsor</strong><div><?= e(humanize($student['sponsor_type'])) ?><?= $student['sponsor_name'] ? ' - ' . e($student['sponsor_name']) : '' ?></div></li>
                </ul>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><?= icon('users') ?> Contact</div>
            <div class="card-body">
                <ul class="list-clean text-muted-sm">
                    <li><strong>Phone</strong><div><?= e($student['phone'] ?? '-') ?></div></li>
                    <li><strong>National ID</strong><div><?= e($student['national_id'] ?? '-') ?></div></li>
                    <li><strong>County</strong><div><?= e($student['county'] ?? '-') ?></div></li>
                    <li><strong>Address</strong><div><?= e($student['physical_address'] ?? '-') ?></div></li>
                    <li><strong>Emergency contact</strong>
                        <div><?= e($student['emergency_contact_name'] ?? '-') ?>
                            <?= $student['emergency_contact_phone'] ? ' &middot; ' . e($student['emergency_contact_phone']) : '' ?></div>
                    </li>
                </ul>
            </div>
        </div>

        <?php if (can('students.status')): ?>
            <div class="card">
                <div class="card-header"><?= icon('shield') ?> Change status</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/students/' . $student['id'] . '/status') ?>"
                          data-confirm="Change this student's status?">
                        <?= csrf_field() ?>
                        <select name="status" class="form-select form-select-sm mb-2">
                            <?= enum_options(['active', 'deferred', 'suspended', 'graduated', 'withdrawn', 'expelled', 'alumni'], $student['status']) ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary w-100">Apply status change</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="stat tone-green">
                    <span class="stat-icon"><?= icon('money', 'ico-lg') ?></span>
                    <div><div class="stat-value"><?= e(money($paid)) ?></div><div class="stat-label">Paid</div></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat <?= $balance > 0 ? 'tone-red' : '' ?>">
                    <span class="stat-icon"><?= icon('money', 'ico-lg') ?></span>
                    <div><div class="stat-value"><?= e(money($balance)) ?></div><div class="stat-label">Balance</div></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat tone-violet">
                    <span class="stat-icon"><?= icon('file', 'ico-lg') ?></span>
                    <div><div class="stat-value"><?= e(money($billed)) ?></div><div class="stat-label">Billed to date</div></div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><?= icon('clipboard') ?> Current semester registration</div>
            <div class="table-wrap">
                <table class="table mb-0">
                    <thead><tr><th>Code</th><th>Course</th><th class="text-center">Credits</th><th>Lecturer</th><th>Approval</th><th class="text-center">Grade</th></tr></thead>
                    <tbody>
                    <?php if ($registrations === []): ?>
                        <tr><td colspan="6"><div class="empty-state"><?= icon('clipboard') ?><p>No units registered this semester.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td><code><?= e($reg['code']) ?></code></td>
                            <td><?= e(str_limit($reg['title'], 42)) ?></td>
                            <td class="text-center"><?= (int) $reg['credit_hours'] ?></td>
                            <td class="text-muted-sm"><?= e($reg['lecturer_name'] ?? '-') ?></td>
                            <td><?= status_badge($reg['approval_status']) ?></td>
                            <td class="text-center">
                                <?php if (!empty($reg['grade']) && (int) $reg['is_published'] === 1): ?>
                                    <span class="badge text-bg-<?= $reg['outcome'] === 'pass' ? 'success' : 'danger' ?>"><?= e($reg['grade']) ?></span>
                                <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><?= icon('chart') ?> Academic history</div>
            <div class="table-wrap">
                <table class="table mb-0">
                    <thead><tr><th>Academic year</th><th>Semester</th><th class="text-center">Credits</th><th class="text-center">GPA</th><th class="text-center">CGPA</th><th>Decision</th></tr></thead>
                    <tbody>
                    <?php if ($history === []): ?>
                        <tr><td colspan="6"><div class="empty-state"><?= icon('chart') ?><p>No completed semesters yet.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($history as $term): ?>
                        <tr>
                            <td><?= e($term['academic_year']) ?></td>
                            <td><?= e($term['semester_name']) ?></td>
                            <td class="text-center"><?= (int) $term['credits_earned'] ?> / <?= (int) $term['credits_registered'] ?></td>
                            <td class="text-center fw-semibold"><?= number_format((float) $term['gpa'], 2) ?></td>
                            <td class="text-center"><?= number_format((float) $term['cgpa'], 2) ?></td>
                            <td><?= status_badge($term['decision']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><?= icon('money') ?> Recent invoices</div>
                    <div class="card-body py-2">
                        <?php if ($invoices === []): ?>
                            <p class="text-muted-sm py-2 mb-0">No invoices raised.</p>
                        <?php else: ?>
                            <ul class="list-clean">
                                <?php foreach ($invoices as $invoice): ?>
                                    <li class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-semibold"><?= e($invoice['invoice_number']) ?></div>
                                            <div class="text-muted-sm"><?= e(str_limit($invoice['title'], 34)) ?></div>
                                        </div>
                                        <div class="text-end">
                                            <div><?= e(money($invoice['balance'])) ?></div>
                                            <?= status_badge($invoice['status']) ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><?= icon('bed') ?> Services</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="fw-semibold small mb-1">Accommodation</div>
                            <?php if ($hostel !== null): ?>
                                <div class="text-muted-sm"><?= e($hostel['hostel_name']) ?> &middot; Room <?= e($hostel['room_number']) ?>
                                    <?= $hostel['bed_number'] ? ' &middot; Bed ' . e($hostel['bed_number']) : '' ?></div>
                            <?php else: ?>
                                <div class="text-muted-sm">Not allocated a room.</div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <div class="fw-semibold small mb-1">Library loans</div>
                            <?php if ($loans === []): ?>
                                <div class="text-muted-sm">No borrowing history.</div>
                            <?php else: ?>
                                <?php foreach ($loans as $loan): ?>
                                    <div class="text-muted-sm"><?= e(str_limit($loan['title'], 34)) ?> &middot; <?= status_badge($loan['status']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="fw-semibold small mb-1">Discipline</div>
                            <?php if ($discipline === []): ?>
                                <div class="text-muted-sm">Clean record.</div>
                            <?php else: ?>
                                <?php foreach ($discipline as $case): ?>
                                    <div class="text-muted-sm"><?= e(str_limit($case['offence'], 34)) ?> &middot; <?= status_badge($case['status']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
