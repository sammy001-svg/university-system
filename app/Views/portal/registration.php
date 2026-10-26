<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Course Registration</h1><p class="lede"><?= e($semester['name'] ?? 'No active semester') ?></p></div>
</div>

<?php if (!$semester): ?>
<div class="alert alert-warning">Course registration is not currently open. Please check with the Registrar's office.</div>
<?php else: ?>

<?php if ($locked !== null): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <?= icon('check') ?>
        <div><strong>Registration Complete.</strong> Your 1st installment is paid and you are enrolled for
            <?= e(($semester['academic_year'] ?? '') . ' ' . $semester['name']) ?>. <?= e($locked) ?>
            <?php if (!empty($enrollment)): ?>
                <a href="<?= url('/portal/finance/invoice/' . $enrollment['invoice_id'] . '/billing-form') ?>">Billing form</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info d-flex align-items-center gap-2">
        <?= icon('clock') ?>
        <div>
            <strong>Choose your courses for the semester.</strong> Your bill is updated each time you add or drop a course
            (tuition is charged per credit hour).
            <?php if (!empty($enrollment)): ?>
                Pay the <strong>1st installment of <?= e(money($enrollment['first_amount'])) ?></strong>
                (<?= e(money($enrollment['first_outstanding'])) ?> outstanding) to complete your registration.
                <a href="<?= url('/portal/finance/invoice/' . $enrollment['invoice_id'] . '/billing-form') ?>">View billing form</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><?= icon('check') ?> Registered Courses <span class="badge text-bg-light"><?= count($registered) ?></span></div>
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Code</th><th>Course</th><th>Credits</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($registered)): ?>
                        <tr><td colspan="5"><div class="empty-state text-sm"><?= icon('course') ?><p>None registered yet.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($registered as $r): ?>
                        <tr>
                            <td><code><?= e($r['code']) ?></code></td>
                            <td><?= e($r['title']) ?></td>
                            <td class="text-center"><?= (int)$r['credit_hours'] ?></td>
                            <td><?= status_badge($r['approval_status'] ?? '') ?></td>
                            <td class="text-end">
                                <?php if ($locked === null): ?>
                                <form method="post" action="<?= url('/portal/registration/' . $r['id']) ?>" onsubmit="return confirm('Drop this course?')">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-outline-danger">Drop</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><?= icon('plus') ?> Available Courses <span class="badge text-bg-light"><?= count($available) ?></span></div>
            <div class="table-wrap">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Code</th><th>Course</th><th>Credits</th><th>Slots</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($available)): ?>
                        <tr><td colspan="5"><div class="empty-state text-sm"><?= icon('course') ?><p>No courses available.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($available as $c): ?>
                        <tr>
                            <td><code><?= e($c['code']) ?></code></td>
                            <td>
                                <div><?= e($c['title']) ?></div>
                                <div class="text-muted-sm"><?= e($c['lecturer_name'] ?? '') ?></div>
                            </td>
                            <td class="text-center"><?= (int)$c['credit_hours'] ?></td>
                            <td class="text-center"><?= (int)$c['capacity'] - (int)$c['enrolled_count'] ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= url('/portal/registration') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="offering_id" value="<?= $c['offering_id'] ?>">
                                    <button class="btn btn-sm btn-primary" <?= $locked !== null ? 'disabled title="Registration is complete"' : '' ?>>Add</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endsection(); ?>
