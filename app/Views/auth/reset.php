<?php
layout('layouts.auth');
$pageTitle = 'Choose a new password';
section('content');
?>
<h1>Choose a new password</h1>
<p class="lede">Your new password must be at least <?= (int) config('security.password_min_length') ?> characters and mix letters with numbers.</p>

<form method="post" action="<?= url('/password/reset') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="email" value="<?= e($email) ?>">

    <div class="mb-3">
        <label class="form-label" for="password">New password <span class="req">*</span></label>
        <input type="password" class="form-control" id="password" name="password" required autofocus>
    </div>
    <div class="mb-4">
        <label class="form-label" for="password_confirmation">Confirm new password <span class="req">*</span></label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
    </div>
    <button class="btn btn-primary w-100 py-2" type="submit">Update password</button>
</form>
<?php endsection(); ?>
