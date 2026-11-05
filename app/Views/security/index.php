<?php
layout('layouts.app');
section('content');
$device = static function (string $agent): string {
    $browser = match (true) {
        str_contains($agent, 'Edg/') => 'Edge', str_contains($agent, 'OPR/') => 'Opera', str_contains($agent, 'Chrome/') => 'Chrome',
        str_contains($agent, 'Firefox/') => 'Firefox', str_contains($agent, 'Safari/') => 'Safari', default => 'Browser',
    };
    $os = match (true) {
        str_contains($agent, 'Android') => 'Android', str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone / iPad',
        str_contains($agent, 'Windows') => 'Windows', str_contains($agent, 'Mac OS') => 'Mac', str_contains($agent, 'Linux') => 'Linux', default => 'Unknown device',
    };
    return $browser . ' on ' . $os;
};
?>
<div class="page-head">
    <div><h1>Account security</h1><p class="lede">Protect your account with two-step sign-in, and see where you are signed in.</p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/profile') ?>">My profile</a>
</div>

<?php if ($required && !$enabled): ?>
    <div class="alert alert-warning"><strong>Two-step sign-in is required for administrator accounts.</strong> Set it up below to continue using the system.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card" id="two-factor">
            <div class="card-header"><?= icon('shield') ?> Two-step sign-in
                <span class="ms-auto"><?= $enabled ? '<span class="badge text-bg-success">On</span>' : '<span class="badge text-bg-light">Off</span>' ?></span></div>
            <div class="card-body">
                <?php if ($newCodes): ?>
                    <div class="alert alert-warning">
                        <strong>Your recovery codes.</strong> Each works once, if you lose your phone. Write them down or print them and keep them safe;
                        they will not be shown again.
                        <div class="row g-1 mt-2 font-monospace">
                            <?php foreach ($newCodes as $c): ?><div class="col-6 col-md-4"><?= e($c) ?></div><?php endforeach; ?>
                        </div>
                        <button class="btn btn-sm btn-light mt-2" onclick="window.print()">Print codes</button>
                    </div>
                <?php endif; ?>

                <?php if ($enabled): ?>
                    <p>Each time you sign in, you also enter a code from your authenticator app. On since <?= fdate($user['two_factor_enabled_at']) ?>;
                        <?= (int) $recoveryLeft ?> recovery code(s) left.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="<?= url('/account/security/recovery-codes') ?>" data-confirm="Create new recovery codes? The old ones will stop working.">
                            <?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">New recovery codes</button></form>
                        <?php if (!$required): ?>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#off2fa">Turn off</button>
                        <?php endif; ?>
                    </div>
                    <form class="collapse mt-3" id="off2fa" method="post" action="<?= url('/account/security/two-factor/disable') ?>">
                        <?= csrf_field() ?>
                        <label class="form-label small">Confirm with your password</label>
                        <div class="input-group" style="max-width:360px"><input class="form-control" type="password" name="password" required autocomplete="current-password">
                            <button class="btn btn-outline-danger">Turn off</button></div>
                    </form>
                <?php elseif ($setupSecret): ?>
                    <ol class="small">
                        <li>Install an authenticator app on your phone (Google Authenticator, Microsoft Authenticator or Authy).</li>
                        <li>In the app, add an account and scan this QR code<span class="text-muted"> - or type the key below</span>.</li>
                        <li>Enter the 6-digit code the app shows.</li>
                    </ol>
                    <div class="d-flex flex-wrap gap-4 align-items-center mb-3">
                        <div data-qr="<?= e($setupUri) ?>" style="width:168px;height:168px;background:#fff;padding:6px;border:1px solid var(--line);border-radius:8px"></div>
                        <div><div class="text-muted-sm">Key</div><code class="fs-6" style="letter-spacing:2px"><?= e(trim(chunk_split($setupSecret, 4, ' '))) ?></code></div>
                    </div>
                    <form method="post" action="<?= url('/account/security/two-factor/enable') ?>">
                        <?= csrf_field() ?>
                        <div class="input-group" style="max-width:300px">
                            <input class="form-control text-center" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" required style="letter-spacing:4px">
                            <button class="btn btn-primary">Turn on</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p>Even if someone learns your password, they cannot sign in without the code from your phone. Recommended for everyone who
                        handles student records or money.</p>
                    <form method="post" action="<?= url('/account/security/two-factor/start') ?>"><?= csrf_field() ?>
                        <button class="btn btn-primary"><?= icon('shield', 'ico-sm') ?> Set up two-step sign-in</button></form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card" id="sessions">
            <div class="card-header"><?= icon('users') ?> Where you're signed in
                <?php if (count($sessions) > 1): ?>
                    <form class="ms-auto" method="post" action="<?= url('/account/security/sessions/others') ?>" data-confirm="Sign out all your other devices?">
                        <?= csrf_field() ?><button class="btn btn-sm btn-link p-0">Sign out all others</button></form>
                <?php endif; ?>
            </div>
            <div class="card-body py-1">
                <?php if ($sessions === []): ?><p class="text-muted-sm py-3 mb-0">No sessions recorded yet.</p><?php endif; ?>
                <?php foreach ($sessions as $sess): $mine = $sess['session_hash'] === $currentHash; ?>
                    <div class="el-item">
                        <div class="el-item-main">
                            <div class="el-item-title"><?= e($device((string) $sess['user_agent'])) ?> <?= $mine ? '<span class="badge text-bg-success">This device</span>' : '' ?></div>
                            <div class="el-item-meta"><?= e((string) $sess['ip_address']) ?> &middot; signed in <?= fdatetime($sess['created_at']) ?> &middot; active <?= ago($sess['last_seen_at']) ?></div>
                        </div>
                        <?php if (!$mine): ?>
                            <form method="post" action="<?= url('/account/security/sessions/' . $sess['id']) ?>"><?= csrf_field() ?>
                                <button class="btn btn-sm btn-light">Sign out</button></form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll('[data-qr]').forEach(function (el) {
  if (typeof QRCode !== 'undefined') { new QRCode(el, { text: el.dataset.qr, width: 154, height: 154, correctLevel: QRCode.CorrectLevel.M }); }
});
</script>
<?php endsection(); ?>
