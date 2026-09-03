<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Record Payment</h1></div>
    <a class="btn btn-sm btn-light" href="<?= url('/payments') ?>">&larr; Back to Payments</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/payments') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" required>
                        <?= options(array_column($students, 'label', 'id'), old('student_id'), 'Select Student') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="amount" class="form-control" value="<?= e(old('amount')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select name="method" class="form-select" required>
                        <?= enum_options(['cash','mpesa','bank_transfer','cheque','card','other'], old('method', 'mpesa')) ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Transaction Reference / Code</label>
                    <input type="text" name="reference" class="form-control" value="<?= e(old('reference')) ?>" placeholder="e.g. M-Pesa Code or Check No.">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" name="paid_at" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Record Payment &amp; Issue Receipt</button>
            <a href="<?= url('/payments') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
