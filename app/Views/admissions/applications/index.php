<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Applications</h1>
        <p class="lede">Student admission applications pipeline.</p>
    </div>
    <?php if (can('admissions.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/admissions/applications/create') ?>">
            <?= icon('plus', 'ico-sm') ?> New Application
        </a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['Submitted', $stats['submitted'] ?? 0, 'file', ''],
        ['Under Review', $stats['under_review'] ?? 0, 'clock', 'tone-amber'],
        ['Accepted', $stats['accepted'] ?? 0, 'check', 'tone-green'],
        ['Enrolled', $stats['enrolled'] ?? 0, 'student', 'tone-green'],
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

<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="search" name="q" class="form-control form-control-sm" value="<?= e($_GET['q'] ?? '') ?>" placeholder="App no, applicant name or email">
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm" data-auto-submit>
                <?= enum_options(['draft','submitted','under_review','shortlisted','accepted','rejected','enrolled','withdrawn'], $_GET['status'] ?? '', 'Any status') ?>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-sm btn-primary">Filter</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header"><?= icon('file') ?> Applications Register <span class="badge text-bg-light"><?= number_format((int)($result['total'] ?? 0)) ?></span></div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>App No.</th>
                    <th>Applicant Name</th>
                    <th>Programme</th>
                    <th>Intake</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($result['data'])): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('file') ?><p>No applications found.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td><code><?= e($row['application_number']) ?></code></td>
                    <td class="fw-semibold"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td><?= e($row['program_name'] ?? '—') ?></td>
                    <td class="text-muted-sm"><?= e($row['intake_name'] ?? '—') ?></td>
                    <td class="text-muted-sm"><?= date_fmt($row['submitted_at'] ?? '') ?></td>
                    <td><?= status_badge($row['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="<?= url('/admissions/applications/' . $row['id']) ?>"><?= icon('eye', 'ico-sm') ?> View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ((int)($result['last_page'] ?? 1) > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center bg-white">
            <span class="text-muted-sm">Page <?= (int)$result['page'] ?> of <?= (int)$result['last_page'] ?></span>
            <?= paginate_links($result) ?>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
