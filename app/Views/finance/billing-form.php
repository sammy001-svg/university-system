<?php layout('layouts.app'); section('content'); ?>
<?php
/** Student's Billing Form, laid out like the Office of Admission and Records paper form. */
$inv     = $form['invoice'];
$name    = trim($inv['last_name'] . ', ' . $inv['first_name'] . ' ' . ($inv['other_name'] ?? ''));
$byNum   = array_column($form['installments'], null, 'installment_number');
$grand   = $form['tuition'] + $form['feesTotal'];
$discount = (float) $inv['discount_amount'];
$usd     = static fn ($amount): string => 'US' . money($amount);
?>
<div class="page-head no-print">
    <div><h1>Student's Billing Form</h1><p class="lede"><?= e($inv['invoice_number']) ?> &middot; <?= status_badge($inv['status']) ?></p></div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('print', 'ico-sm') ?> Print</button>
        <a class="btn btn-sm btn-light" href="<?= url($backUrl) ?>">&larr; Back</a>
    </div>
</div>

<div class="card p-4 billing-form">
    <div class="d-flex align-items-center gap-3 mb-3">
        <?php if (!empty($appLogo)): ?>
            <img src="<?= e(uploaded($appLogo)) ?>" alt="" style="height:72px">
        <?php endif; ?>
        <div class="flex-grow-1 text-center">
            <div class="fw-bold fs-5"><?= e($appName) ?></div>
            <h2 class="h4 fw-bold mb-0">Student's Billing Form</h2>
            <div class="text-muted">Office of Admission and Records</div>
        </div>
    </div>

    <table class="table table-bordered table-sm mb-4">
        <tr>
            <td><strong>Student:</strong> <?= e($name) ?></td>
            <td><strong>Sex:</strong> <?= e(humanize($inv['gender'] ?? '')) ?></td>
            <td><strong>ID:</strong> <?= e($inv['admission_number']) ?></td>
        </tr>
        <tr>
            <td><strong>Department:</strong> <?= e(preg_replace('/^Department of /', '', $inv['department_name'])) ?></td>
            <td><strong>College:</strong> <?= e($inv['college_name']) ?></td>
            <td><strong>Tel:</strong> <?= e($inv['phone'] ?? '') ?></td>
        </tr>
        <tr>
            <td><strong>Country of Origin:</strong> <?= e($inv['nationality'] ?? '') ?></td>
            <td><strong>E-mail:</strong> <?= e($inv['email'] ?? '') ?></td>
            <td><strong>Academic Year:</strong> <?= e($inv['academic_year'] ?? '') ?></td>
        </tr>
        <tr>
            <td><strong>Semester:</strong> <?= e($inv['semester_name'] ?? '') ?></td>
            <td><strong>Status:</strong> <?= e($form['standing']) ?></td>
            <td><strong>Programme:</strong> <?= e($inv['program_name']) ?></td>
        </tr>
    </table>

    <table class="table table-bordered table-sm align-middle mb-4">
        <thead class="table-light">
            <tr><th>Course Code</th><th>Course Title</th><th class="text-center">Section</th><th class="text-center">Credit Hours</th><th class="text-end">Cost</th><th>Instructor</th></tr>
        </thead>
        <tbody>
        <?php if (empty($form['courses'])): ?>
            <tr><td colspan="6" class="text-center text-muted">No courses registered yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($form['courses'] as $course): ?>
            <tr>
                <td><?= e($course['code']) ?><?php if (!empty($course['billing_codes'])): ?><div class="text-muted small">also <?= e($course['billing_codes']) ?></div><?php endif; ?></td>
                <td><?= e($course['title']) ?></td>
                <td class="text-center"><?= e($course['section'] ?? '') ?></td>
                <td class="text-center"><?= (int) $course['quantity'] ?></td>
                <td class="text-end" title="<?= (int) $course['quantity'] ?> × <?= e(money($course['unit_amount'])) ?>"><?= e($usd($course['amount'])) ?></td>
                <td><?= e($course['instructor'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-center"><?= (int) $form['credits'] ?></td><td class="text-end text-primary"><?= e($usd($form['tuition'])) ?></td><td></td></tr>
            <tr class="fw-bold"><td colspan="4">Total Registration Fees</td><td class="text-end text-danger"><?= e($usd($form['feesTotal'])) ?></td><td></td></tr>
            <?php if ($discount > 0): ?>
            <tr><td colspan="4">Scholarship / Discount</td><td class="text-end">-<?= e($usd($discount)) ?></td><td></td></tr>
            <?php endif; ?>
            <tr class="fw-bold"><td colspan="4">Grand Total Registration and Tuition for the semester</td><td class="text-end"><?= e($usd($grand - $discount)) ?></td><td></td></tr>
        </tfoot>
    </table>

    <div class="row g-4">
        <div class="col-md-7">
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-light"><tr><th>Activity</th><th class="text-end">Cost</th></tr></thead>
                <tbody>
                <?php foreach ($form['fees'] as $fee): ?>
                    <tr><td><?= e($fee['description']) ?></td><td class="text-end"><?= e(number_format((float) $fee['amount'], 2)) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-bold"><td class="text-end">Total</td><td class="text-end"><?= e($usd($form['feesTotal'])) ?></td></tr></tfoot>
            </table>
        </div>
        <div class="col-md-5">
            <table class="table table-bordered table-sm">
                <?php foreach ($byNum as $number => $installment): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($installment['label']) ?> Total<?= $number === 1 ? ' <span class="badge text-bg-danger">Compulsory</span>' : '' ?>
                            <div class="text-muted small">Due <?= e(date_fmt($installment['due_date'] ?? '')) ?> &middot; <?= e(humanize($installment['status'])) ?></div></td>
                        <td class="text-end fw-bold"><?= e(money($installment['amount'])) ?> <span class="text-muted small">USD</span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="small text-muted">The 1st installment covers all registration fees and half of the tuition. Registration is complete once it is paid in full.</p>
            <div class="border p-3 text-center mb-3" style="min-height:90px">
                <div style="margin-top:36px">.......................................</div>
                <div class="fw-semibold">Signature: Business &amp; Finance Office</div>
            </div>
            <div class="border p-3 text-center" style="min-height:90px">
                <div style="margin-top:36px">.......................................</div>
                <div class="fw-semibold">Approved: Admission &amp; Records</div>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
