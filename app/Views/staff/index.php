<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Staff</h1>
        <p class="lede">All academic and administrative staff members.</p>
    </div>
    <?php if (can('staff.create')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/staff/create') ?>">
            <?= icon('plus', 'ico-sm') ?> Add Staff
        </a>
    <?php endif; ?>
</div>

<form class="filter-bar" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="search" name="q" class="form-control form-control-sm"
                   value="<?= e($_GET['q'] ?? '') ?>" placeholder="Staff no, name, email…">
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm" data-auto-submit>
                <?= enum_options(['active','on_leave','suspended','terminated','retired'], $_GET['status'] ?? '', 'Any status') ?>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-sm btn-primary">Apply</button>
            <?php if (!empty(array_filter($_GET))): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/staff') ?>">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <?= icon('staff') ?> Staff register
        <span class="badge text-bg-light"><?= number_format((int)$result['total']) ?></span>
        <span class="ms-auto text-muted-sm fw-normal">
            <?php if ((int)$result['total'] > 0): ?>
                Showing <?= (int)$result['from'] ?>&ndash;<?= (int)$result['to'] ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Staff No.</th>
                <th>Name</th>
                <th>Designation</th>
                <th>Department</th>
                <th>Category</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($result['data'] === []): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('staff') ?><p>No staff members found.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($result['data'] as $row):
                $name = trim(($row['title'] ?? '') . ' ' . $row['first_name'] . ' ' . $row['last_name']); ?>
                <tr>
                    <td><code><?= e($row['staff_number']) ?></code></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm" style="background:<?= e(avatar_color($name)) ?>">
                                <?php if (!empty($row['avatar'])): ?>
                                    <img src="<?= uploaded($row['avatar']) ?>" alt="">
                                <?php else: ?><?= e(initials($row['first_name'], $row['last_name'])) ?><?php endif; ?>
                            </span>
                            <div>
                                <a class="fw-semibold" href="<?= url('/staff/' . $row['id']) ?>"><?= e($name) ?></a>
                                <div class="text-muted-sm"><?= e($row['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= e($row['designation'] ?? '—') ?></td>
                    <td><?= e($row['department_name'] ?? '—') ?></td>
                    <td><?= status_badge($row['staff_category'] ?? '') ?></td>
                    <td><?= status_badge($row['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" title="Profile" href="<?= url('/staff/' . $row['id']) ?>"><?= icon('eye', 'ico-sm') ?></a>
                        <?php if (can('staff.edit')): ?>
                            <a class="btn btn-sm btn-light" title="Edit" href="<?= url('/staff/' . $row['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ((int)$result['last_page'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center bg-white">
            <span class="text-muted-sm">Page <?= (int)$result['page'] ?> of <?= (int)$result['last_page'] ?></span>
            <?= paginate_links($result) ?>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
