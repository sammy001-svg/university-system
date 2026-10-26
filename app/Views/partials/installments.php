<?php
/**
 * Installment schedule for an invoice.
 * Expects $installments (rows from invoice_installments) and optional $enrollment
 * (from SemesterEnrollment::forStudent).
 */
?>
<?php if (!empty($enrollment)): ?>
    <?php if ($enrollment['status'] === 'enrolled'): ?>
        <div class="alert alert-success d-flex align-items-center gap-2">
            <?= icon('check') ?>
            <div><strong>Registration Complete.</strong> 1st installment paid; enrolled for the semester
                <?= !empty($enrollment['enrolled_at']) ? 'on ' . e(date_fmt($enrollment['enrolled_at'])) : '' ?>.</div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <?= icon('clock') ?>
            <div><strong>Not yet enrolled.</strong> The 1st installment is compulsory. Pay
                <?= e(money($enrollment['first_outstanding'])) ?> to be enrolled for the semester and complete registration.</div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($installments)): ?>
<div class="card mb-4">
    <div class="card-header"><?= icon('calendar') ?> Installment Schedule</div>
    <div class="table-wrap">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Installment</th>
                    <th>Due Date</th>
                    <th class="text-end">Amount (<?= e(currency_code()) ?>)</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Outstanding</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($installments as $inst): ?>
                <tr>
                    <td class="fw-semibold">
                        <?= e($inst['label']) ?>
                        <?php if ((int) $inst['installment_number'] === 1): ?>
                            <span class="badge text-bg-danger ms-1">Compulsory</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted-sm"><?= e(date_fmt($inst['due_date'] ?? '')) ?></td>
                    <td class="text-end"><?= e(money($inst['amount'])) ?></td>
                    <td class="text-end"><?= e(money($inst['amount_paid'])) ?></td>
                    <td class="text-end"><?= e(money(max(0, (float) $inst['amount'] - (float) $inst['amount_paid']))) ?></td>
                    <td><?= status_badge($inst['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted-sm">
        All registration, examination and other non-tuition fees are due with the 1st installment; tuition is spread across the installments.
    </div>
</div>
<?php endif; ?>
