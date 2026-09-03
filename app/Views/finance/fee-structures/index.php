<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Fee Structures</h1>
        <p class="lede">Institution fee schedules per program &amp; academic year.</p>
    </div>
    <?php if (can('finance.manage')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/fee-structures/create') ?>">
            <?= icon('plus', 'ico-sm') ?> New Fee Structure
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('finance') ?> Fee Structures (<?= count($structures) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Title / Name</th>
                    <th>Program</th>
                    <th>Academic Year</th>
                    <th>Study Mode</th>
                    <th>Year of Study</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($structures)): ?>
                <tr><td colspan="6"><div class="empty-state"><?= icon('finance') ?><p>No fee structures created.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($structures as $s): ?>
                <tr>
                    <td class="fw-semibold"><?= e($s['name']) ?></td>
                    <td><?= e($s['program_name'] ?? 'All Programs') ?></td>
                    <td><?= e($s['academic_year'] ?? '—') ?></td>
                    <td><?= e(humanize($s['study_mode'] ?? 'full_time')) ?></td>
                    <td>Year <?= (int)$s['year_of_study'] ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" href="<?= url('/fee-structures/' . $s['id']) ?>"><?= icon('eye', 'ico-sm') ?> View Items</a>
                        <?php if (can('finance.manage')): ?>
                            <a class="btn btn-sm btn-light" href="<?= url('/fee-structures/' . $s['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?> Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
