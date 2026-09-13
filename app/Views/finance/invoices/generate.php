<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Generate Batch Invoices</h1><p class="lede">Generate fee invoices for students based on fee structure rules.</p></div>
    <a class="btn btn-sm btn-light" href="<?= url('/finance/invoices') ?>">&larr; Back to Invoices</a>
</div>

<div class="card">
    <form method="post" action="<?= url('/finance/invoices/generate') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Fee Structure <span class="text-danger">*</span></label>
                    <select name="fee_structure_id" class="form-select" required>
                        <?= options(array_column($feeStructures, 'name', 'id'), '', 'Select Fee Structure') ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Due Date <span class="text-danger">*</span></label>
                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">Generate Invoices</button>
            <a href="<?= url('/finance/invoices') ?>" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>
<?php endsection(); ?>
