<?php
use App\Services\Payments\OnlinePaymentService;

layout('layouts.app');
section('content');
$balance  = round((float) $invoice['balance'], 2);
$chosen   = (string) old_raw('provider', array_key_first($providers) ?? '');
$amount   = old_raw('amount', $firstDue ?? $balance);
?>
<div class="page-head">
    <div>
        <h1>Pay fees online</h1>
        <p class="lede"><?= e($student['first_name'] . ' ' . $student['last_name']) ?> &middot; <?= e($student['admission_number']) ?>
            &middot; invoice <?= e($invoice['invoice_number']) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url($backUrl) ?>">&larr; Back</a>
</div>

<?php if ($providers === []): ?>
    <div class="alert alert-info">Online payment is not set up yet. Please pay at the Finance Office.</div>
<?php else: ?>
<div class="row g-3">
    <div class="col-lg-7">
        <?php foreach ($pending as $p): ?>
            <div class="alert alert-warning">A payment of <strong><?= e(money($p['amount'])) ?></strong> started <?= ago($p['created_at']) ?> is
                <?= $p['status'] === 'pending' ? 'waiting for approval on the phone' : 'waiting for the Finance Office to confirm it' ?>.
                <a href="<?= url('/pay/status/' . $p['reference']) ?>">View</a></div>
        <?php endforeach; ?>

        <form class="card" method="post" enctype="multipart/form-data" action="<?= url('/pay/' . $student['id'] . '/' . $invoice['id']) ?>" data-pay-form>
            <?= csrf_field() ?>
            <div class="card-header"><?= icon('money') ?> Payment</div>
            <div class="card-body">
                <label class="form-label">Amount</label>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php if ($firstDue !== null): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-amount="<?= e((string) $firstDue) ?>">1st installment &middot; <?= e(money($firstDue)) ?></button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-amount="<?= e((string) $balance) ?>">Full balance &middot; <?= e(money($balance)) ?></button>
                </div>
                <div class="input-group mb-1" style="max-width:260px">
                    <span class="input-group-text"><?= e((string) setting('currency_symbol', '$')) ?></span>
                    <input class="form-control" type="number" name="amount" min="1" max="<?= e((string) $balance) ?>" step="0.01" required value="<?= e((string) $amount) ?>">
                </div>
                <?php if ($firstDue !== null): ?>
                    <div class="form-text mb-3">Paying the 1st installment (<?= e(money($firstDue)) ?>) completes registration for the semester.</div>
                <?php endif; ?>

                <label class="form-label mt-2">Pay with</label>
                <?php foreach ($providers as $key => $instructions): ?>
                    <label class="exam-choice">
                        <input class="form-check-input" type="radio" name="provider" value="<?= e($key) ?>" <?= $chosen === $key ? 'checked' : '' ?> required>
                        <span><span class="fw-semibold d-block"><?= e(OnlinePaymentService::PROVIDERS[$key]) ?></span>
                            <span class="text-muted-sm" style="white-space:pre-line"><?= e($instructions) ?></span></span>
                    </label>
                <?php endforeach; ?>

                <div class="mt-3" data-for="mtn_momo">
                    <label class="form-label">MTN number that will pay</label>
                    <input class="form-control" name="payer_phone" inputmode="tel" value="<?= e((string) old_raw('payer_phone', $student['phone'] ?? '')) ?>" placeholder="e.g. 0881 234 567" style="max-width:260px">
                </div>
                <div class="mt-3" data-for="orange_money mtn_manual bank_transfer">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Transaction ID</label>
                            <input class="form-control" name="provider_reference" value="<?= e((string) old_raw('provider_reference', '')) ?>" placeholder="From the confirmation SMS or slip">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone used <span class="text-muted-sm">(optional)</span></label>
                            <input class="form-control" name="payer_phone" inputmode="tel" data-alt-phone>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Screenshot or slip <span class="text-muted-sm">(optional, JPG/PNG/PDF)</span></label>
                            <input class="form-control" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer"><button class="btn btn-primary"><?= icon('money', 'ico-sm') ?> Continue</button></div>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><?= icon('file') ?> Invoice <?= e($invoice['invoice_number']) ?></div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted fw-normal">Total</dt><dd class="col-6 text-end"><?= e(money((float) $invoice['total_amount'] - (float) $invoice['discount_amount'])) ?></dd>
                    <dt class="col-6 text-muted fw-normal">Paid so far</dt><dd class="col-6 text-end"><?= e(money($invoice['amount_paid'])) ?></dd>
                    <dt class="col-6 fw-semibold">Outstanding</dt><dd class="col-6 text-end fw-bold"><?= e(money($balance)) ?></dd>
                </dl>
                <p class="text-muted-sm mt-3 mb-0">Your receipt is issued automatically once the payment is confirmed, and you are notified by message.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endsection(); ?>

<?php section('scripts'); ?>
<script>
(function () {
  var form = document.querySelector('[data-pay-form]');
  if (!form) { return; }
  form.querySelectorAll('[data-amount]').forEach(function (b) {
    b.addEventListener('click', function () { form.querySelector('[name=amount]').value = b.dataset.amount; });
  });
  var sync = function () {
    var p = (form.querySelector('[name=provider]:checked') || {}).value;
    form.querySelectorAll('[data-for]').forEach(function (el) {
      var on = el.dataset.for.split(' ').indexOf(p) >= 0;
      el.style.display = on ? '' : 'none';
      el.querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
    });
  };
  form.querySelectorAll('[name=provider]').forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>
<?php endsection(); ?>
