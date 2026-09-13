<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Course Registration</h1><p class="lede"><?= e($semester['name'] ?? 'No active semester') ?></p></div>
</div>

<?php if (!$semester): ?>
<div class="alert alert-warning">Course registration is not currently open. Please check with the Registrar's office.</div>
<?php else: ?>

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
                                <form method="post" action="<?= url('/portal/registration/' . $r['id'] . '/drop') ?>" onsubmit="return confirm('Drop this course?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger">Drop</button>
                                </form>
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
                                    <button class="btn btn-sm btn-primary">Register</button>
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
