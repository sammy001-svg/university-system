<?php
layout('layouts.auth');
$pageTitle = 'Sign in';
section('content');
?>
<h1>Welcome back</h1>
<p class="lede">Sign in with your university account to continue.</p>

<form method="post" action="<?= url('/login') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label" for="identifier">Username or email <span class="req">*</span></label>
        <input type="text" class="form-control <?= has_error('identifier') ? 'is-invalid' : '' ?>"
               id="identifier" name="identifier" value="<?= old('identifier') ?>"
               placeholder="e.g. jdoe or jdoe@university.ac.ke" autofocus required>
        <?php if (has_error('identifier')): ?>
            <div class="invalid-feedback d-block"><?= error_for('identifier') ?></div>
        <?php endif; ?>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <label class="form-label mb-0" for="password">Password <span class="req">*</span></label>
            <a class="small" href="<?= url('/password/forgot') ?>">Forgot password?</a>
        </div>
        <input type="password" class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>"
               id="password" name="password" placeholder="Enter your password" required>
        <?php if (has_error('password')): ?>
            <div class="invalid-feedback d-block"><?= error_for('password') ?></div>
        <?php endif; ?>
    </div>

    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
        <label class="form-check-label small" for="remember">Keep me signed in on this device</label>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2">Sign in</button>
</form>

<div class="divider"></div>
<p class="text-muted-sm mb-0">
    Applying for admission? <a href="<?= url('/apply') ?>">Start an application</a>.
</p>
<p class="text-muted-sm">Trouble signing in? Contact the ICT helpdesk on
    <a href="mailto:<?= e(setting('support_email', 'ict@university.ac.ke')) ?>"><?= e(setting('support_email', 'ict@university.ac.ke')) ?></a>.
</p>
<?php endsection(); ?>
