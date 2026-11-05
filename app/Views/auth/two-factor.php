<?php
layout('layouts.auth');
$pageTitle = 'Two-step sign-in';
section('content');
?>
<h1>Two-step sign-in</h1>
<p class="lede">Open your authenticator app and enter the 6-digit code for <?= e($appName) ?>.</p>

<form method="post" action="<?= url('/login/two-factor') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="code">Code</label>
        <input class="form-control form-control-lg text-center" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
               maxlength="13" placeholder="123456" autofocus required style="letter-spacing:6px">
        <div class="form-text">Lost your phone? Enter one of your recovery codes (like <code>A1B2C3-D4E5F6</code>) instead.</div>
    </div>
    <button class="btn btn-primary w-100 py-2">Verify and sign in</button>
</form>
<p class="text-muted-sm mt-3"><a href="<?= url('/login') ?>">&larr; Use a different account</a></p>
<?php endsection(); ?>
