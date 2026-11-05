<?php
use App\Services\Academic\RequestService;

layout('layouts.app');
section('content');
$tabs = ['open' => 'Open', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Withdrawn', 'all' => 'All'];
$open = (int) ($counts['submitted'] ?? 0) + (int) ($counts['under_review'] ?? 0);
?>
<div class="page-head"><div><h1>Student Requests</h1><p class="lede">Leave of absence, deferment, withdrawal, change of programme, grade appeals and other petitions.</p></div></div>
<nav class="el-tabs">
    <?php foreach ($tabs as $k => $l): ?>
        <a class="<?= $status === $k ? 'active' : '' ?>" href="<?= url('/requests?status=' . $k) ?>"><?= $l ?>
            <?php $n = $k === 'open' ? $open : (int) ($counts[$k] ?? 0); if ($k !== 'all' && $n): ?><span class="badge text-bg-<?= $k === 'open' ? 'warning' : 'light' ?>"><?= $n ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
<form class="filter-bar" method="get"><input type="hidden" name="status" value="<?= e($status) ?>"><div class="row g-2">
    <div class="col-md-4"><select class="form-select form-select-sm" name="type" data-auto-submit><?= options(RequestService::TYPES, $_GET['type'] ?? '', 'All request types') ?></select></div></div></form>
<div class="card">
    <div class="table-wrap"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Reference</th><th>Student</th><th>Request</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="6"><div class="empty-state"><?= icon('clipboard') ?><p>No requests here.</p></div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><code><?= e($r['reference']) ?></code></td>
                <td><div class="fw-semibold"><?= e($r['last_name'] . ', ' . $r['first_name']) ?></div><div class="text-muted-sm"><?= e($r['admission_number']) ?> &middot; <?= e($r['program_name']) ?></div></td>
                <td><?= e(RequestService::TYPES[$r['request_type']]) ?><div class="text-muted-sm"><?= e(str_limit($r['details'], 70)) ?></div></td>
                <td class="text-muted-sm text-nowrap"><?= fdate($r['created_at']) ?><div><?= ago($r['created_at']) ?></div></td>
                <td><?= status_badge($r['status']) ?></td>
                <td class="text-end"><a class="btn btn-sm <?= in_array($r['status'], ['submitted', 'under_review'], true) ? 'btn-primary' : 'btn-light' ?>" href="<?= url('/requests/' . $r['id']) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
</div>
<?php endsection(); ?>
