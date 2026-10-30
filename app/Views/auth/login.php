<?php
layout('layouts.auth');
$pageTitle = 'Sign in';
section('content');
$as = $_GET['as'] ?? old_raw('login_as', 'staff');
$as = $as === 'student' ? 'student' : 'staff';
$fields = [
    'staff'   => ['label' => 'Username or email', 'placeholder' => 'e.g. jdoe or jdoe@bestbrain.edu.lr',
                  'hint'  => 'Administrators, lecturers and staff.'],
    'student' => ['label' => 'Admission number', 'placeholder' => 'e.g. BBIU2026100162',
                  'hint'  => 'Use the admission number on your billing form or login slip.'],
];
?>
<h1>Welcome back</h1>
<p class="lede">Sign in with your university account to continue.</p>

<form method="post" action="<?= url('/login') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="login-as" role="radiogroup" aria-label="Sign in as">
        <input type="radio" name="login_as" id="as-staff" value="staff" <?= $as === 'staff' ? 'checked' : '' ?>>
        <label for="as-staff"><?= icon('users') ?> Staff</label>
        <input type="radio" name="login_as" id="as-student" value="student" <?= $as === 'student' ? 'checked' : '' ?>>
        <label for="as-student"><?= icon('student') ?> Student</label>
    </div>

    <div class="mb-3">
        <label class="form-label" for="identifier"><span data-field="label"><?= e($fields[$as]['label']) ?></span> <span class="req">*</span></label>
        <input type="text" class="form-control <?= has_error('identifier') ? 'is-invalid' : '' ?>"
               id="identifier" name="identifier" value="<?= old('identifier') ?>"
               placeholder="<?= e($fields[$as]['placeholder']) ?>" autocomplete="username" autofocus required>
        <?php if (has_error('identifier')): ?>
            <div class="invalid-feedback d-block"><?= error_for('identifier') ?></div>
        <?php endif; ?>
        <div class="form-text" data-field="hint"><?= e($fields[$as]['hint']) ?></div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <label class="form-label mb-0" for="password">Password <span class="req">*</span></label>
            <a class="small" href="<?= url('/password/forgot') ?>">Forgot password?</a>
        </div>
        <input type="password" class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>"
               id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
        <?php if (has_error('password')): ?>
            <div class="invalid-feedback d-block"><?= error_for('password') ?></div>
        <?php endif; ?>
    </div>

    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
        <label class="form-check-label small" for="remember">Keep me signed in on this device</label>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2" data-field="submit">Sign in as <?= $as === 'student' ? 'student' : 'staff' ?></button>
</form>

<div class="divider"></div>
<p class="text-muted-sm mb-0">
    Applying for admission? <a href="<?= url('/apply') ?>">Start an application</a>.
</p>
<p class="text-muted-sm">Trouble signing in? Contact the ICT helpdesk on
    <a href="mailto:<?= e(setting('support_email', 'ict@bestbrain.edu.lr')) ?>"><?= e(setting('support_email', 'ict@bestbrain.edu.lr')) ?></a>.
</p>

<script>
(function () {
    var fields = <?= json_encode($fields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var input = document.getElementById('identifier');
    document.querySelectorAll('input[name="login_as"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var f = fields[radio.value];
            document.querySelector('[data-field="label"]').textContent = f.label;
            document.querySelector('[data-field="hint"]').textContent = f.hint;
            document.querySelector('[data-field="submit"]').textContent = 'Sign in as ' + radio.value;
            input.placeholder = f.placeholder;
            input.value = '';
            input.focus();
        });
    });
})();
</script>
<?php endsection(); ?>
