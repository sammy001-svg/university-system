<?php
use App\Services\Payments\OnlinePaymentService;

layout('layouts.app');
section('content');
$state = $op['status'];
?>
<div class="page-head">
    <div><h1>Payment status</h1><p class="lede"><?= e(OnlinePaymentService::PROVIDERS[$op['provider']]) ?> &middot; <?= e(money($op['amount'])) ?> &middot; invoice <?= e($op['invoice_number']) ?></p></div>
    <a class="btn btn-sm btn-light" href="<?= url($backUrl) ?>">&larr; Fees</a>
</div>

<div class="card" style="max-width:640px">
    <div class="card-body text-center py-5" data-pay-status data-state="<?= e($state) ?>" data-check="<?= e(url('/pay/status/' . $op['reference'] . '/check')) ?>">
        <?php if ($state === 'successful'): ?>
            <div class="text-success mb-2"><?= icon('check', 'ico-lg') ?></div>
            <h2 class="h4">Payment received</h2>
            <p class="mb-1"><?= e(money($op['amount'])) ?> has been posted to <?= e($op['first_name']) ?>'s account.</p>
            <p class="text-muted">Receipt <strong><?= e((string) $op['receipt_number']) ?></strong></p>
        <?php elseif ($state === 'pending'): ?>
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <h2 class="h4">Approve the payment on your phone</h2>
            <p class="text-muted">A prompt has been sent to <strong><?= e((string) $op['payer_phone']) ?></strong>. Enter your MoMo PIN to approve
                <?= e(money($op['amount'])) ?>. This page updates by itself.</p>
        <?php elseif ($state === 'awaiting_verification'): ?>
            <div class="text-warning mb-2"><?= icon('clock', 'ico-lg') ?></div>
            <h2 class="h4">Waiting for the Finance Office to confirm</h2>
            <p class="text-muted">We have your transaction ID <strong><?= e((string) $op['provider_reference']) ?></strong>. Once the Finance Office
                matches it with the money received, your receipt is issued and you are notified.</p>
        <?php else: ?>
            <div class="text-danger mb-2"><?= icon('shield', 'ico-lg') ?></div>
            <h2 class="h4">Payment <?= e($state === 'rejected' ? 'not confirmed' : 'not completed') ?></h2>
            <p class="text-muted"><?= e((string) ($op['message'] ?: 'The payment did not go through.')) ?></p>
            <a class="btn btn-primary" href="<?= url('/pay/' . $op['student_id'] . '/' . $op['invoice_id']) ?>">Try again</a>
        <?php endif; ?>
        <div class="text-muted-sm mt-3">Reference <?= e($op['reference']) ?></div>
    </div>
</div>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
(function () {
  var box = document.querySelector('[data-pay-status]');
  if (!box || box.dataset.state !== 'pending') { return; }
  var tries = 0;
  var poll = function () {
    if (++tries > 60) { return; }
    fetch(box.dataset.check, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.status !== 'pending') { location.reload(); } else { setTimeout(poll, 5000); } })
      .catch(function () { setTimeout(poll, 8000); });
  };
  setTimeout(poll, 5000);
})();
</script>
<?php endsection(); ?>
