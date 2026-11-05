<?php
layout('layouts.app');
section('content');
?>
<div class="page-head">
    <div><h1>Signed-in sessions</h1><p class="lede">Everyone active in the last 7 days. Sign out a lost or shared device, or every device of an account.</p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/admin/settings?group=security') ?>">Security settings</a>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($twoFactor as $t): ?>
        <div class="col-6 col-md-4 col-xl-3"><div class="mini-stat"><b><?= (int) $t['on_count'] ?> / <?= (int) $t['total'] ?></b>
            <span><?= e(ucfirst($t['user_type'])) ?> accounts with two-step sign-in</span></div></div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Account</th><th>Device</th><th>IP address</th><th>Signed in</th><th>Last active</th><th></th></tr></thead>
            <tbody>
            <?php if ($sessions === []): ?><tr><td colspan="6"><div class="empty-state"><?= icon('users') ?><p>No active sessions.</p></div></td></tr><?php endif; ?>
            <?php foreach ($sessions as $s): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
                        <div class="text-muted-sm"><?= e($s['username']) ?> &middot; <?= e($s['user_type']) ?> <?= $s['has_2fa'] ? '&middot; <span class="text-success">2-step on</span>' : '' ?></div></td>
                    <td class="text-muted-sm" style="max-width:320px"><?= e(str_limit((string) $s['user_agent'], 80)) ?></td>
                    <td><code><?= e((string) $s['ip_address']) ?></code></td>
                    <td class="text-muted-sm"><?= fdatetime($s['created_at']) ?></td>
                    <td class="text-muted-sm"><?= ago($s['last_seen_at']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('users.edit')): ?>
                            <form class="d-inline" method="post" action="<?= url('/admin/sessions/' . $s['id'] . '/revoke') ?>"><?= csrf_field() ?>
                                <button class="btn btn-sm btn-light">Sign out</button></form>
                            <form class="d-inline" method="post" action="<?= url('/admin/sessions/' . $s['id'] . '/revoke') ?>" data-confirm="Sign this account out on every device?">
                                <?= csrf_field() ?><input type="hidden" name="all" value="1"><button class="btn btn-sm btn-light text-danger">All devices</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
