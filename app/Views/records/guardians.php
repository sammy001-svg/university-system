<?php
layout('layouts.app');
section('content');
$s = $student;
?>
<div class="page-head">
    <div>
        <h1>Parents and sponsors</h1>
        <p class="lede"><?= e(trim($s['first_name'] . ' ' . $s['last_name'])) ?> &middot; <?= e($s['admission_number']) ?>. A parent with portal access can see
            this student's registration, results, attendance and fees, and pay fees online. They cannot change anything.</p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url('/students/' . $s['id']) ?>">&larr; Student</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Name</th><th>Contact</th><th>Portal access</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($guardians === []): ?>
                        <tr><td colspan="4"><div class="empty-state"><?= icon('users') ?><p>No parents or sponsors recorded.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($guardians as $g): ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e($g['full_name']) ?> <?= (int) $g['is_primary'] === 1 ? '<span class="badge text-bg-light">Primary</span>' : '' ?></div>
                                <div class="text-muted-sm"><?= e(ucfirst($g['relationship'])) ?><?= $g['occupation'] ? ' &middot; ' . e($g['occupation']) : '' ?></div></td>
                            <td class="small"><?= e((string) $g['phone']) ?><div class="text-muted-sm"><?= e((string) $g['email']) ?></div></td>
                            <td class="small">
                                <?php if ($g['user_id']): ?>
                                    <code><?= e($g['username']) ?></code>
                                    <?= $g['awaiting'] ? '<span class="badge text-bg-warning">Login slip not printed</span>' : ($g['last_login_at'] ? '<div class="text-muted-sm">last signed in ' . ago($g['last_login_at']) . '</div>' : '<div class="text-muted-sm">never signed in</div>') ?>
                                    <?= (int) $g['linked_students'] > 1 ? '<div class="text-muted-sm">' . (int) $g['linked_students'] . ' students linked</div>' : '' ?>
                                <?php else: ?><span class="text-muted">None</span><?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if (can('students.edit')): ?>
                                    <?php if (!$g['user_id'] && can('users.create')): ?>
                                        <form class="d-inline" method="post" action="<?= url('/guardians/' . $g['id'] . '/access') ?>"><?= csrf_field() ?>
                                            <button class="btn btn-sm btn-primary">Give portal access</button></form>
                                    <?php elseif ($g['user_id']): ?>
                                        <?php if ($g['awaiting'] && can('users.reset')): ?><a class="btn btn-sm btn-light" href="<?= url('/admin/data-imports/slips?type=parent') ?>">Login slip</a><?php endif; ?>
                                        <form class="d-inline" method="post" action="<?= url('/guardians/' . $g['id'] . '/access') ?>" data-confirm="Remove portal access for this student?">
                                            <?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn btn-sm btn-light">Remove access</button></form>
                                    <?php endif; ?>
                                    <form class="d-inline" method="post" action="<?= url('/guardians/' . $g['id']) ?>" data-confirm="Remove <?= e($g['full_name']) ?>?">
                                        <?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn btn-sm btn-light text-danger"><?= icon('trash', 'ico-sm') ?></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if (can('students.edit')): ?>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?= icon('plus') ?> Add a parent or sponsor</div>
                <div class="card-body">
                    <form method="post" action="<?= url('/students/' . $s['id'] . '/guardians') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2"><label class="form-label">Full name <span class="req">*</span></label><input class="form-control" name="full_name" required></div>
                        <div class="mb-2"><label class="form-label">Relationship</label>
                            <select class="form-select" name="relationship"><?= enum_options(['father', 'mother', 'guardian', 'sponsor', 'spouse', 'sibling', 'other'], 'guardian') ?></select></div>
                        <div class="mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" inputmode="tel"></div>
                        <div class="mb-2"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div>
                        <div class="mb-2"><label class="form-label">Occupation / organisation</label><input class="form-control" name="occupation"></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_primary" value="1" id="pri"><label class="form-check-label" for="pri">Primary contact</label></div>
                        <button class="btn btn-primary w-100">Add</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endsection(); ?>
