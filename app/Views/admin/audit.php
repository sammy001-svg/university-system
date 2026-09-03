<?php
layout('layouts.app');
$pageTitle = 'Audit Trail';
section('content');
$rows = $result['data'];
?>
<div class="page-head">
    <div>
        <h1>Audit Trail</h1>
        <p class="lede">Every create, update, delete and sign-in recorded by the system.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm btn-icon" href="<?= e(query_with(['export' => 'csv'])) ?>">
        <?= icon('download', 'ico-sm') ?> Export CSV
    </a>
</div>

<form class="filter-bar" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input type="search" name="q" class="form-control form-control-sm" value="<?= e($_GET['q'] ?? '') ?>"
                   placeholder="Description, entity or user">
        </div>
        <div class="col-md-2">
            <label class="form-label">Module</label>
            <select name="module" class="form-select form-select-sm" data-auto-submit>
                <?= enum_options($modules, $_GET['module'] ?? '', 'All modules') ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Action</label>
            <select name="action" class="form-select form-select-sm" data-auto-submit>
                <?= enum_options($actions, $_GET['action'] ?? '', 'All actions') ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= e($_GET['from'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= e($_GET['to'] ?? '') ?>">
        </div>
        <div class="col-md-1">
            <button class="btn btn-sm btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <?= icon('shield') ?> Activity
        <span class="badge text-bg-light"><?= number_format((int) $result['total']) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table table-hover mb-0">
            <thead>
            <tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Entity</th><th>Description</th><th>IP</th></tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7"><div class="empty-state"><?= icon('shield') ?><p>No audit entries match your filters.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="text-nowrap text-muted-sm">
                        <div><?= fdatetime($row['created_at']) ?></div>
                        <div><?= ago($row['created_at']) ?></div>
                    </td>
                    <td>
                        <?php if ($row['actor_name'] !== null): ?>
                            <div class="fw-semibold"><?= e($row['actor_name']) ?></div>
                            <div class="text-muted-sm"><?= e($row['actor_username']) ?></div>
                        <?php else: ?>
                            <span class="text-muted">System</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-<?= status_class($row['action']) ?>"><?= e(humanize($row['action'])) ?></span></td>
                    <td class="text-muted-sm"><?= e(humanize($row['module'] ?? '')) ?></td>
                    <td class="text-muted-sm">
                        <?= e($row['entity'] ?? '') ?><?= $row['entity_id'] ? ' #' . e($row['entity_id']) : '' ?>
                    </td>
                    <td><?= e(str_limit($row['description'], 70)) ?></td>
                    <td class="text-muted-sm"><?= e($row['ip_address'] ?? '') ?></td>
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
