<?php
/** One staff member's payslip, on the printable document sheet. */
layout('layouts.document');
section('content');
$p    = $payslip;
$name = (string) preg_replace('/\s+/', ' ', trim(($p['title'] ?? '') . ' ' . $p['first_name'] . ' ' . ($p['other_name'] ?? '') . ' ' . $p['last_name']));
$row  = static fn (string $k, string $v, bool $strong = false): string =>
    '<tr><td>' . e($k) . '</td><td class="num' . ($strong ? ' b' : '') . '">' . e($v) . '</td></tr>';
?>
<style>
    .pay-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 16px; }
    .pay-grid table { width: 100%; border-collapse: collapse; font-family: system-ui, sans-serif; font-size: 12.5px; }
    .pay-grid th { text-align: left; background: #f2ecf3; border: 1px solid #000; padding: 5px 8px; }
    .pay-grid td { border: 1px solid #000; padding: 4px 8px; }
    .pay-grid td.num { text-align: right; font-variant-numeric: tabular-nums; }
    .pay-grid td.b { font-weight: 700; }
    .net-line { margin-top: 16px; border: 2px solid #000; padding: 8px 12px; display: flex; justify-content: space-between;
                font-family: system-ui, sans-serif; font-size: 15px; font-weight: 700; }
</style>

<div class="toolbar text-center py-3">
    <button class="btn btn-primary btn-sm" onclick="window.print()">Print / save as PDF</button>
    <a class="btn btn-light btn-sm" href="<?= url('/finance/payroll/' . $p['payroll_period_id']) ?>">Back to the register</a>
</div>

<div class="sheet">
    <?php partial('partials.record-letterhead', ['office' => 'PAYSLIP &middot; ' . strtoupper((string) $p['period_name']), 'compact' => true]) ?>

    <table class="grid-box">
        <tr>
            <td style="width:40%"><span class="k">Name:</span> <b><?= e($name) ?></b></td>
            <td style="width:30%"><span class="k">Employee ID:</span> <b><?= e($p['staff_number']) ?></b></td>
            <td style="width:30%"><span class="k">Payroll date:</span> <b><?= e(fdate($p['pay_date'])) ?></b></td>
        </tr>
        <tr>
            <td><span class="k">Position:</span> <b><?= e((string) ($p['designation'] ?? 'N/A')) ?></b></td>
            <td><span class="k">Department:</span> <b><?= e((string) ($p['department_name'] ?? 'N/A')) ?></b></td>
            <td><span class="k">Status:</span> <b><?= e(humanize((string) $p['status'])) ?></b></td>
        </tr>
        <tr>
            <td><span class="k">Bank:</span> <b><?= e((string) ($p['bank_name'] ?? 'N/A')) ?></b></td>
            <td><span class="k">Account:</span> <b><?= e((string) ($p['bank_account'] ?? 'N/A')) ?></b></td>
            <td><span class="k">Phone:</span> <b><?= e((string) ($p['phone'] ?? 'N/A')) ?></b></td>
        </tr>
    </table>

    <div class="pay-grid">
        <table>
            <thead><tr><th>Earnings</th><th style="text-align:right;width:40%">Amount</th></tr></thead>
            <tbody>
            <?= $row('Basic salary', money($p['basic_salary'], false)) ?>
            <?php foreach ($lines as $l): if ($l['kind'] !== 'allowance') { continue; } ?>
                <?= $row($l['label'] . ((int) $l['taxable'] === 0 ? ' (not taxed)' : ''), money($l['amount'], false)) ?>
            <?php endforeach; ?>
            <?= $row('Gross income', money($p['gross_pay'], false), true) ?>
            </tbody>
        </table>

        <table>
            <thead><tr><th>Deductions</th><th style="text-align:right;width:40%">Amount</th></tr></thead>
            <tbody>
            <?= $row('Taxable amount', money($p['taxable_amount'], false)) ?>
            <?= $row('Income tax', money($p['tax_deduction'], false)) ?>
            <?php foreach ($lines as $l): if ($l['kind'] !== 'deduction') { continue; } ?>
                <?= $row($l['label'], money($l['amount'], false)) ?>
            <?php endforeach; ?>
            <?php foreach ($loans as $l): ?>
                <?= $row(ucfirst(str_replace('_', ' ', (string) $l['kind'])) . ' ' . $l['reference'], money($l['amount'], false)) ?>
            <?php endforeach; ?>
            <?= $row('Total deductions', money($p['total_deductions'], false), true) ?>
            </tbody>
        </table>
    </div>

    <div class="net-line"><span>NET SALARY</span><span><?= e(money($p['net_pay'])) ?></span></div>

    <?php if ((float) $p['outstanding_balance'] > 0): ?>
        <p style="font-family:system-ui;font-size:12px;margin-top:10px">Outstanding balance carried forward:
            <b><?= e(money($p['outstanding_balance'])) ?></b><?php if ($loans !== []): ?> (after this month's repayment)<?php endif; ?>.</p>
    <?php endif; ?>
    <?php if (!empty($p['notes'])): ?>
        <p style="font-family:system-ui;font-size:12px"><b>Note:</b> <?= e($p['notes']) ?></p>
    <?php endif; ?>

    <div class="signature" style="margin-top:38px">
        <div><div style="height:38px"></div><div class="line">Payroll Officer<br><span style="font-size:12px;color:#666">Finance Office</span></div></div>
        <div><div style="height:38px"></div><div class="line">Received by<br><span style="font-size:12px;color:#666">Signature and date</span></div></div>
    </div>

    <div class="doc-foot">
        <span>Payslip for <?= e($p['period_name']) ?> &middot; printed <?= e(date('m/d/Y')) ?></span>
        <span class="motto"><?= e((string) setting('institution_motto', '')) ?></span>
        <span><?= e((string) setting('institution_email', '')) ?></span>
    </div>
</div>

<?php if ($p['status'] !== 'paid' && can('payroll.edit')): ?>
    <div class="toolbar text-center pb-4">
        <form method="post" action="<?= url('/finance/payroll/payslip/' . $p['id']) ?>" class="d-inline-flex gap-2 align-items-center">
            <?= csrf_field() ?>
            <input type="text" name="notes" class="form-control form-control-sm" style="width:320px" maxlength="255"
                   value="<?= e((string) ($p['notes'] ?? '')) ?>" placeholder="Note on this payslip">
            <button class="btn btn-sm btn-light">Save note &amp; recalculate</button>
        </form>
    </div>
<?php endif; ?>
<?php endsection(); ?>
