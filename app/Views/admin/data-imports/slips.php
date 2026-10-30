<?php
layout('layouts.app');
$pageTitle = 'Login Slips';
section('content');
$never = ($_GET['scope'] ?? '') === 'never';
?>
<div class="page-head">
    <div>
        <h1>Login Slips</h1>
        <p class="lede">Print a slip with a username and a temporary password for each person. The password is shown
            once, on the slips; each person must choose their own at first sign-in.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/admin/data-imports') ?>">&larr; Data Imports</a>
</div>

<form class="filter-bar" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="search" name="q" class="form-control form-control-sm" value="<?= e($_GET['q'] ?? '') ?>"
                   placeholder="Name, username, student ID or staff number">
        </div>
        <div class="col-md-2">
            <label class="form-label">Account type</label>
            <select name="type" class="form-select form-select-sm" data-auto-submit>
                <?= options($types, $_GET['type'] ?? '', 'All types') ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Show</label>
            <select name="scope" class="form-select form-select-sm" data-auto-submit>
                <?= options(['' => 'Accounts still waiting for a first password', 'never' => 'Every account that has never signed in (reprint a lost slip)'],
                    $never ? 'never' : '') ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<form method="post" action="<?= url('/admin/data-imports/slips') ?>"
      data-confirm="Give the ticked accounts a new temporary password and print their slips? Any slip printed earlier for them stops working.">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <?= icon('users') ?> Accounts
            <span class="badge text-bg-light"><?= count($accounts) ?></span>
            <button class="btn btn-primary btn-sm btn-icon ms-auto" <?= $accounts === [] ? 'disabled' : '' ?>>
                <?= icon('print', 'ico-sm') ?> Print slips for ticked accounts
            </button>
        </div>
        <div class="table-wrap">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th style="width:1%"><input type="checkbox" class="form-check-input" checked
                               onclick="document.querySelectorAll('[data-slip]').forEach(c => c.checked = this.checked)"></th>
                    <th>Name</th><th>Username</th><th>ID</th><th>Programme / position</th><th>College</th><th>Password</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($accounts === []): ?>
                    <tr><td colspan="7"><div class="empty-state"><?= icon('check') ?>
                        <p><?= $never ? 'No account matches.' : 'Every imported account already has a password.' ?></p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($accounts as $a): ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= (int) $a['id'] ?>" data-slip checked></td>
                        <td class="fw-semibold"><?= e(trim($a['last_name'] . ', ' . $a['first_name'] . ' ' . ($a['other_name'] ?? ''))) ?>
                            <div class="text-muted-sm"><?= e(ucfirst($a['user_type'])) ?></div></td>
                        <td><code><?= e($a['username']) ?></code></td>
                        <td class="text-muted-sm"><?= e($a['id_number'] ?? '') ?></td>
                        <td class="text-muted-sm"><?= e($a['detail'] ?? '') ?></td>
                        <td class="text-muted-sm"><?= e($a['college'] ?? '') ?></td>
                        <td><?= $a['awaiting'] ? '<span class="badge text-bg-warning">Not issued</span>' : '<span class="badge text-bg-secondary">Issued, unused</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
<?php endsection(); ?>
