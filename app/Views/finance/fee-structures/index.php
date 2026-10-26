<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Fee Structures</h1>
        <p class="lede">Student bills by status, department or college. A student is billed on the most specific active structure that matches them.</p>
    </div>
    <?php if (can('finance.manage')): ?>
        <a class="btn btn-primary btn-sm btn-icon" href="<?= url('/finance/fee-structures/create') ?>">
            <?= icon('plus', 'ico-sm') ?> New Fee Structure
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('money') ?> Fee Structures (<?= count($structures) ?>)</div>
    <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Applies To</th>
                    <th>Student Status</th>
                    <th>Academic Year</th>
                    <th>Year / Mode</th>
                    <th class="text-end">Total (<?= e(currency_code()) ?>)</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($structures)): ?>
                <tr><td colspan="8"><div class="empty-state"><?= icon('money') ?><p>No fee structures created.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($structures as $s): ?>
                <tr>
                    <td class="fw-semibold"><?= e($s['name']) ?></td>
                    <td><?= e(\App\Models\FeeStructure::scopeLabel($s)) ?></td>
                    <td><?= e(\App\Models\FeeStructure::STATUSES[$s['student_status']] ?? humanize($s['student_status'])) ?></td>
                    <td><?= e($s['academic_year'] ?? '—') ?></td>
                    <td class="text-muted-sm">
                        <?= $s['year_of_study'] !== null ? 'Year ' . (int) $s['year_of_study'] : 'Any year' ?>
                        <?= $s['semester_number'] !== null ? ' · Sem ' . (int) $s['semester_number'] : '' ?>
                        · <?= e($s['study_mode'] !== null ? humanize($s['study_mode']) : 'Any mode') ?>
                    </td>
                    <td class="text-end"><?= e(money($s['total_amount'])) ?></td>
                    <td><?= status_badge($s['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-light" href="<?= url('/finance/fee-structures/' . $s['id']) ?>"><?= icon('eye', 'ico-sm') ?> View</a>
                        <?php if (can('finance.manage')): ?>
                            <a class="btn btn-sm btn-light" href="<?= url('/finance/fee-structures/' . $s['id'] . '/edit') ?>"><?= icon('edit', 'ico-sm') ?> Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endsection(); ?>
