<?php
layout('layouts.app');
$pageTitle = 'Students';
section('content');
$rows = $result['data'];
?>
<div class="page-head">
    <div>
        <h1>Students</h1>
        <p class="lede">The student register with programme, year of study and academic standing.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm btn-icon" href="<?= url('/students/export') . (count($_GET) ? '?' . http_build_query($_GET) : '') ?>">
            <?= icon('download', 'ico-sm') ?> Export
        </a>
        <?php if (can('students.create')): ?>
            <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/students/create') ?>">
                <?= icon('plus', 'ico-sm') ?> Admit student
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Active', $counts['active'] ?? 0, 'student', ''],
        ['Deferred', $counts['deferred'] ?? 0, 'clock', 'tone-amber'],
        ['Graduated', $counts['graduated'] ?? 0, 'check', 'tone-green'],
        ['Suspended / withdrawn', ($counts['suspended'] ?? 0) + ($counts['withdrawn'] ?? 0), 'shield', 'tone-red'],
    ];
    foreach ($tiles as [$label, $value, $iconName, $tone]): ?>
        <div class="col-6 col-xl-3">
            <div class="stat <?= $tone ?>">
                <span class="stat-icon"><?= icon($iconName, 'ico-lg') ?></span>
                <div>
                    <div class="stat-value"><?= number_format((int) $value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<form class="filter-bar" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input type="search" name="q" class="form-control form-control-sm" value="<?= e($_GET['q'] ?? '') ?>"
                   placeholder="Admission no, name or email">
        </div>
        <div class="col-md-3">
            <label class="form-label">Programme</label>
            <select name="program_id" class="form-select form-select-sm" data-auto-submit>
                <?= options($programs, $_GET['program_id'] ?? '', 'All programmes') ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Year</label>
            <select name="year_of_study" class="form-select form-select-sm" data-auto-submit>
                <?= options([1 => 'Year 1', 2 => 'Year 2', 3 => 'Year 3', 4 => 'Year 4', 5 => 'Year 5'], $_GET['year_of_study'] ?? '', 'All years') ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm" data-auto-submit>
                <?= enum_options(['active', 'deferred', 'suspended', 'graduated', 'withdrawn', 'expelled', 'alumni'], $_GET['status'] ?? '', 'Any status') ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-primary w-100">Apply</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <?= icon('student') ?> Student register
        <span class="badge text-bg-light"><?= number_format((int) $result['total']) ?></span>
        <span class="ms-auto text-muted-sm fw-normal">
            <?php if ((int) $result['total'] > 0): ?>
                Showing <?= (int) $result['from'] ?>&ndash;<?= (int) $result['to'] ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Admission no</th><th>Student</th><th>Programme</th>
                <th class="text-center">Year</th><th class="text-center">CGPA</th>
                <th>Status</th><th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('student') ?><p>No students match your filters.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $name = trim($row['first_name'] . ' ' . $row['last_name']); ?>
                <tr>
                    <td><code><?= e($row['admission_number']) ?></code></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm" style="background:<?= e(avatar_color($name)) ?>">
                                <?php if (!empty($row['avatar'])): ?>
                                    <img src="<?= uploaded($row['avatar']) ?>" alt="">
                                <?php else: ?><?= e(initials($row['first_name'], $row['last_name'])) ?><?php endif; ?>
                            </span>
                            <div>
                                <a class="fw-semibold" href="<?= url('/students/' . $row['id']) ?>"><?= e($name) ?></a>
                                <div class="text-muted-sm"><?= e($row['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?= e(str_limit($row['program_name'], 38)) ?></div>
                        <div class="text-muted-sm"><?= e($row['program_code']) ?> &middot; <?= e(humanize($row['study_mode'])) ?></div>
                    </td>
                    <td class="text-center"><?= (int) $row['year_of_study'] ?></td>
                    <td class="text-center fw-semibold"><?= number_format((float) $row['cgpa'], 2) ?></td>
                    <td><?= status_badge($row['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" title="Profile" href="<?= url('/students/' . $row['id']) ?>"><?= icon('eye', 'ico-sm') ?></a>
                        <?php if (can('results.view')): ?>
                            <a class="btn btn-sm btn-light" title="Transcript" href="<?= url('/students/' . $row['id'] . '/transcript') ?>"><?= icon('file', 'ico-sm') ?></a>
                        <?php endif; ?>
                        <?php if (can('students.edit')): ?>
                            <a class="btn btn-sm btn-light" title="Edit" href="<?= url('/students/' . $row['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?></a>
                        <?php endif; ?>
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
