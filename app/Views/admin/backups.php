<?php
layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div><h1>Backups</h1><p class="lede">Full copies of the database. One is made automatically each day (Settings &rsaquo; Security), and the oldest are removed
        once the limit is reached. Download them regularly and keep a copy off the server.</p></div>
    <form method="post" action="<?= url('/admin/backups') ?>"><?= csrf_field() ?>
        <button class="btn btn-primary btn-sm btn-icon"><?= icon('download', 'ico-sm') ?> Back up now</button></form>
</div>

<?php if ($backups === []): ?>
    <div class="alert alert-warning">There is no backup yet. Press <strong>Back up now</strong>.</div>
<?php elseif (time() - $backups[0]['time'] > 2 * 86400): ?>
    <div class="alert alert-warning">The newest backup is <?= e(ago(date('Y-m-d H:i:s', $backups[0]['time']))) ?>. Check that daily backups are switched on.</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Backup</th><th>Created</th><th class="text-end">Size</th><th></th></tr></thead>
            <tbody>
            <?php if ($backups === []): ?><tr><td colspan="4"><div class="empty-state"><?= icon('shield') ?><p>No backups.</p></div></td></tr><?php endif; ?>
            <?php foreach ($backups as $b): ?>
                <tr>
                    <td><code><?= e($b['name']) ?></code></td>
                    <td class="text-muted-sm"><?= e(date('d M Y H:i', $b['time'])) ?> &middot; <?= e(ago(date('Y-m-d H:i:s', $b['time']))) ?></td>
                    <td class="text-end"><?= number_format($b['size'] / 1048576, 2) ?> MB</td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" href="<?= url('/admin/backups/' . $b['name']) ?>"><?= icon('download', 'ico-sm') ?> Download</a>
                        <form class="d-inline" method="post" action="<?= url('/admin/backups/' . $b['name']) ?>" data-confirm="Delete this backup?">
                            <?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn btn-sm btn-light text-danger"><?= icon('trash', 'ico-sm') ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted-sm mt-3">Stored in <code><?= e($dir) ?></code>. To restore one: <code>gunzip &lt; backup-....sql.gz | mysql -u USER -p DATABASE</code>.
    For a backup from the server's scheduler, run <code>php database/backup.php</code> (e.g. nightly with cron).</p>
<?php endsection(); ?>
