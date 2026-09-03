<?php
layout('layouts.auth');
$pageTitle = 'Reset password';
section('content');
?>
<h1>Reset your password</h1>
<p class="lede">Enter the email address on your account and we will send a reset link.</p>

<form method="post" action="<?= url('/password/forgot') ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="email">Email address <span class="req">*</span></label>
        <input type="email" class="form-control" id="email" name="email" value="<?= old('email') ?>" required autofocus>
    </div>
    <button class="btn btn-primary w-100 py-2" type="submit">Send reset link</button>
</form>

<div class="divider"></div>
<a href="<?= url('/login') ?>" class="small">&larr; Back to sign in</a>
<?php endsection(); ?>
