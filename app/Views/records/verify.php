<?php
use App\Services\Academic\DocumentService;

layout('layouts.auth');
$pageTitle = 'Verify a document';
section('content');
$expired = $doc && $doc['valid_until'] && $doc['valid_until'] < date('Y-m-d');
?>
<h1>Verify a document</h1>
<p class="lede">Check that a letter, transcript or ID card issued by <?= e($appName) ?> is genuine.</p>

<form method="get" action="<?= url('/verify') ?>" class="mb-4">
    <label class="form-label" for="code">Verification code</label>
    <div class="input-group">
        <input class="form-control text-uppercase" id="code" name="code" value="<?= e($code) ?>" placeholder="e.g. 7KQ2M9XHTA" required autocomplete="off">
        <button class="btn btn-primary">Verify</button>
    </div>
    <div class="form-text">The code is printed beside the QR code on the document.</div>
</form>

<?php if ($code !== '' && $doc === null): ?>
    <div class="alert alert-danger"><strong>No document matches this code.</strong> It was not issued by <?= e($appName) ?>, or the code was mistyped.</div>
<?php elseif ($doc !== null): ?>
    <div class="alert alert-<?= $doc['status'] === 'revoked' ? 'danger' : ($expired ? 'warning' : 'success') ?> mb-3">
        <?php if ($doc['status'] === 'revoked'): ?>
            <strong>Revoked.</strong> This document was issued but has since been withdrawn and is no longer valid.
        <?php elseif ($expired): ?>
            <strong>Genuine but expired</strong> on <?= fdate($doc['valid_until']) ?>.
        <?php else: ?>
            <strong>Genuine and valid.</strong> This document was issued by <?= e($appName) ?>.
        <?php endif; ?>
    </div>
    <table class="table table-sm small">
        <tr><th class="text-muted fw-normal">Document</th><td class="fw-semibold"><?= e(DocumentService::TYPES[$doc['doc_type']]) ?></td></tr>
        <tr><th class="text-muted fw-normal">Serial</th><td><?= e($doc['serial']) ?></td></tr>
        <tr><th class="text-muted fw-normal">Issued</th><td><?= fdate($doc['issued_at']) ?></td></tr>
        <?php if ($doc['valid_until']): ?><tr><th class="text-muted fw-normal">Valid until</th><td><?= fdate($doc['valid_until']) ?></td></tr><?php endif; ?>
        <?php foreach ($summary as $k => $v): ?><tr><th class="text-muted fw-normal"><?= e((string) $k) ?></th><td><?= e((string) $v) ?></td></tr><?php endforeach; ?>
    </table>
    <p class="text-muted-sm">Compare these details with the document in front of you. If they differ, the document has been altered.</p>
<?php endif; ?>
<p class="text-muted-sm mt-3 mb-0"><a href="<?= url('/login') ?>">&larr; Sign in</a></p>
<?php endsection(); ?>
