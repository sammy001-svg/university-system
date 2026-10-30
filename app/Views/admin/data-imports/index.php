<?php
layout('layouts.app');
$pageTitle = 'Data Imports';
section('content');
$typeLabels = ['billing_forms' => "Students' billing forms", 'student_list' => 'Semester student list',
               'lecturers' => 'Lecturers', 'staff' => 'Staff'];
$awaitingTotal = array_sum(array_column($awaiting, 'n'));
$pending = count(array_filter($files, static fn (array $f): bool => $f['row'] === null || $f['changed']));
?>
<div class="page-head">
    <div>
        <h1>Data Imports</h1>
        <p class="lede">Files in <code>database/data/</code> are imported automatically on the first page load after they
            are added or changed. Each person is matched to their existing record, so nothing is duplicated.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('users.reset')): ?>
            <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/admin/data-imports/slips') ?>">
                <?= icon('print', 'ico-sm') ?> Login slips
                <?php if ($awaitingTotal > 0): ?><span class="badge text-bg-light"><?= $awaitingTotal ?></span><?php endif; ?>
            </a>
        <?php endif; ?>
        <form method="post" action="<?= url('/admin/data-imports/run') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary btn-sm btn-icon"><?= icon('download', 'ico-sm') ?> Import pending now</button>
        </form>
    </div>
</div>

<?php if ($awaitingTotal > 0): ?>
    <div class="alert alert-info">
        <strong><?= $awaitingTotal ?></strong> imported account<?= $awaitingTotal === 1 ? ' has' : 's have' ?> no password yet
        (<?= e(implode(', ', array_map(static fn (array $a): string => $a['n'] . ' ' . ($a['user_type'] === 'staff' ? 'staff' : $a['user_type'] . ($a['n'] > 1 ? 's' : '')), $awaiting))) ?>).
        Nobody can sign in to them until you print their <a href="<?= url('/admin/data-imports/slips') ?>">login slips</a>.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <?= icon('file') ?> Data files
        <span class="badge text-bg-light"><?= count($files) ?></span>
        <?php if ($pending > 0): ?><span class="badge text-bg-warning"><?= $pending ?> pending</span><?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="table table-hover mb-0">
            <thead>
            <tr><th>File</th><th>Contents</th><th>Status</th><th class="text-end">Created</th><th class="text-end">Updated</th>
                <th class="text-end">Failed</th><th>Imported</th><th></th></tr>
            </thead>
            <tbody>
            <?php if ($files === []): ?>
                <tr><td colspan="8"><div class="empty-state"><?= icon('file') ?><p>No data files in database/data/.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($files as $name => $f): $row = $f['row']; ?>
                <tr>
                    <td class="fw-semibold"><?= e($name) ?></td>
                    <td class="text-muted-sm">
                        <?= e($typeLabels[$row['import_type'] ?? ''] ?? '') ?>
                        <?php if (!empty($row['source'])): ?><div><?= e($row['source']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row === null): ?>
                            <span class="badge text-bg-warning">Waiting</span>
                        <?php else: ?>
                            <?= status_badge($row['status'] === 'ok' ? 'completed' : $row['status']) ?>
                            <?php if ($f['changed']): ?><span class="badge text-bg-warning">Changed - re-import due</span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= $row ? (int) $row['created_count'] : '' ?></td>
                    <td class="text-end"><?= $row ? (int) $row['updated_count'] : '' ?></td>
                    <td class="text-end <?= $row && $row['failed_count'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= $row ? (int) $row['failed_count'] : '' ?></td>
                    <td class="text-nowrap text-muted-sm"><?= $row ? fdatetime($row['imported_at']) : '' ?></td>
                    <td class="text-end">
                        <?php if ($row !== null): ?>
                            <a class="btn btn-sm btn-light" href="<?= url('/admin/data-imports/' . $row['id']) ?>" title="Report"><?= icon('eye', 'ico-sm') ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($removed as $name => $row): ?>
                <tr class="text-muted">
                    <td><?= e($name) ?></td>
                    <td class="text-muted-sm"><?= e($typeLabels[$row['import_type'] ?? ''] ?? '') ?></td>
                    <td><span class="badge text-bg-secondary">File removed</span></td>
                    <td class="text-end"><?= (int) $row['created_count'] ?></td>
                    <td class="text-end"><?= (int) $row['updated_count'] ?></td>
                    <td class="text-end"><?= (int) $row['failed_count'] ?></td>
                    <td class="text-nowrap text-muted-sm"><?= fdatetime($row['imported_at']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-light" href="<?= url('/admin/data-imports/' . $row['id']) ?>" title="Report"><?= icon('eye', 'ico-sm') ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="text-muted-sm mt-3">
    To add or correct records, change the file and deploy it: the next page load imports it again, updating the
    people already in the system. A file that failed is tried again whenever the system is updated, or with
    <em>Import pending now</em>.
</p>
<?php endsection(); ?>
