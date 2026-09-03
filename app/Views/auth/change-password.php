<?php
layout('layouts.app');
$pageTitle = 'Change password';
section('content');
?>
<div class="page-head">
    <div>
        <h1>Change password</h1>
        <p class="lede">Keep your account secure with a strong, unique password.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><?= icon('shield') ?> Update your password</div>
            <div class="card-body">
                <form method="post" action="<?= url('/password/change') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password <span class="req">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">New password <span class="req">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Confirm new password <span class="req">*</span></label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Update password</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><?= icon('bell') ?> Password guidance</div>
            <div class="card-body">
                <ul class="list-clean text-muted-sm">
                    <li>At least <?= (int) config('security.password_min_length') ?> characters long.</li>
                    <li>Contains both letters and numbers.</li>
                    <li>Not reused from another service.</li>
                    <li>Never shared &mdash; staff will never ask for it.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
