<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Class Offerings</h1>
        <p class="lede">Active course sections offered across academic terms.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('offerings.create')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="#" onclick="event.preventDefault(); document.getElementById('generate-form').submit();">
                <?= icon('refresh', 'ico-sm') ?> Auto-Generate Offerings
            </a>
            <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/offerings/create') ?>">
                <?= icon('plus', 'ico-sm') ?> New Offering
            </a>
        <?php endif; ?>
    </div>
</div>

<form id="generate-form" method="post" action="<?= url('/offerings/generate') ?>" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="semester_id" value="<?= e($semesters[0]['id'] ?? '') ?>">
</form>

<div class="card">
    <div class="card-header">
        <?= icon('course') ?> All Offerings
        <span class="badge text-bg-light"><?= number_format((int)($result['total'] ?? 0)) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Code</th>
                <th>Course Title</th>
                <th>Section</th>
                <th>Semester</th>
                <th>Lecturer</th>
                <th>Enrolled / Cap</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($result['data'])): ?>
                <tr><td colspan="8"><div class="empty-state"><?= icon('course') ?><p>No course offerings found.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td><code><?= e($row['code'] ?? '') ?></code></td>
                    <td class="fw-semibold"><?= e($row['title'] ?? '') ?></td>
                    <td><span class="badge text-bg-secondary">Sec <?= e($row['section'] ?? '1') ?></span></td>
                    <td class="text-muted-sm"><?= e($row['semester_name'] ?? '') ?></td>
                    <td><?= e($row['lecturer_name'] ?? 'Unassigned') ?></td>
                    <td><?= (int)($row['enrolled_count'] ?? 0) ?> / <?= (int)($row['capacity'] ?? 0) ?></td>
                    <td><?= status_badge($row['status'] ?? 'open') ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" title="Class Roster" href="<?= url('/offerings/' . $row['id'] . '/roster') ?>"><?= icon('student', 'ico-sm') ?></a>
                        <?php if (can('offerings.edit')): ?>
                            <a class="btn btn-sm btn-light" title="Edit" href="<?= url('/offerings/' . $row['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?></a>
                        <?php endif; ?>
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
