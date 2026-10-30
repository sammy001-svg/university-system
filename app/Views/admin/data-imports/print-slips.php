<?php
layout('layouts.app');
section('content');
?>
<style>
    .slip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 12px; }
    .slip { border: 1px dashed #9a9a9a; border-radius: 6px; padding: 14px 16px; background: #fff; break-inside: avoid; page-break-inside: avoid; }
    .slip .slip-head { font-weight: 700; border-bottom: 1px solid #e3e3e3; padding-bottom: 6px; margin-bottom: 8px; }
    .slip dl { display: grid; grid-template-columns: auto 1fr; gap: 2px 10px; margin: 0 0 8px; }
    .slip dt { font-weight: 500; color: #666; }
    .slip dd { margin: 0; }
    .slip .secret { font-family: ui-monospace, Consolas, monospace; font-size: 1.05em; font-weight: 700; letter-spacing: .03em; }
    @media print { .slip-grid { grid-template-columns: 1fr 1fr; } }
</style>

<div class="page-head no-print">
    <div>
        <h1>Login Slips</h1>
        <p class="lede"><?= count($slips) ?> slip<?= count($slips) === 1 ? '' : 's' ?>. Print them now: the passwords are
            not kept and will not be shown again. Leaving this page without printing means issuing new ones.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-primary" onclick="window.print()"><?= icon('print', 'ico-sm') ?> Print</button>
        <a class="btn btn-sm btn-light" href="<?= url('/admin/data-imports/slips') ?>">&larr; Back</a>
    </div>
</div>

<div class="slip-grid">
    <?php foreach ($slips as $s): ?>
        <div class="slip">
            <div class="slip-head"><?= e($appName) ?> &middot; Sign-in details</div>
            <dl>
                <dt>Name</dt><dd><?= e((string) preg_replace('/\s+/', ' ', trim($s['title'] . ' ' . $s['first_name'] . ' ' . $s['other_name'] . ' ' . $s['last_name']))) ?></dd>
                <?php if (!empty($s['id_number'])): ?><dt><?= $s['user_type'] === 'student' ? 'Student ID' : 'Staff no.' ?></dt><dd><?= e($s['id_number']) ?></dd><?php endif; ?>
                <?php if (!empty($s['detail'])): ?><dt><?= $s['user_type'] === 'student' ? 'Programme' : 'Position' ?></dt><dd><?= e($s['detail']) ?></dd><?php endif; ?>
                <dt>Website</dt><dd><?= e($loginUrl) ?></dd>
                <dt>Username</dt><dd class="secret"><?= e($s['username']) ?></dd>
                <dt>Password</dt><dd class="secret"><?= e($s['password']) ?></dd>
            </dl>
            <div class="small text-muted">Temporary password: you will be asked to choose your own when you first sign in.
                Keep this slip private.</div>
        </div>
    <?php endforeach; ?>
</div>
<?php endsection(); ?>
