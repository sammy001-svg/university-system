<?php
layout('layouts.app');
$pageTitle = 'Dashboard';
section('content');
?>
<div class="page-head">
    <div>
        <h1>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>,
            <?= e($currentUser['first_name'] ?? '') ?></h1>
        <p class="lede">
            <?php if ($semester !== null): ?>
                Current period: <strong><?= e($semester['academic_year']) ?></strong> &middot; <?= e($semester['name']) ?>
                <?php if (!empty($semester['registration_end'])): ?>
                    &middot; registration closes <?= fdate($semester['registration_end']) ?>
                <?php endif; ?>
            <?php else: ?>
                No academic semester is currently active.
            <?php endif; ?>
        </p>
    </div>
    <div class="text-muted-sm text-end">
        <div><?= date('l, d F Y') ?></div>
        <div><?= e(humanize($currentUser['user_type'] ?? '')) ?> account</div>
    </div>
</div>

<?php if ($attention !== []): ?>
    <div class="card mb-3">
        <div class="card-header"><?= icon('bell') ?> Needs your attention</div>
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($attention as $item): ?>
                    <a class="btn btn-sm btn-outline-<?= e($item['tone']) ?>" href="<?= url($item['url']) ?>">
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['label' => 'Active students', 'value' => $stats['students'],  'icon' => 'student',   'tone' => ''],
        ['label' => 'Staff',           'value' => $stats['staff'],     'icon' => 'users',     'tone' => 'tone-teal'],
        ['label' => 'Programmes',      'value' => $stats['programs'],  'icon' => 'book',      'tone' => 'tone-violet'],
        ['label' => 'Courses',         'value' => $stats['courses'],   'icon' => 'clipboard', 'tone' => 'tone-amber'],
    ];
    foreach ($tiles as $tile): ?>
        <div class="col-6 col-xl-3">
            <div class="stat <?= e($tile['tone']) ?>">
                <span class="stat-icon"><?= icon($tile['icon'], 'ico-lg') ?></span>
                <div>
                    <div class="stat-value"><?= number_format((int) $tile['value']) ?></div>
                    <div class="stat-label"><?= e($tile['label']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($finance !== null): ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="stat tone-green">
                <span class="stat-icon"><?= icon('money', 'ico-lg') ?></span>
                <div>
                    <div class="stat-value"><?= e(money($finance['collected'])) ?></div>
                    <div class="stat-label">Fees collected</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat tone-red">
                <span class="stat-icon"><?= icon('money', 'ico-lg') ?></span>
                <div>
                    <div class="stat-value"><?= e(money($finance['outstanding'])) ?></div>
                    <div class="stat-label">Outstanding</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat tone-amber">
                <span class="stat-icon"><?= icon('clock', 'ico-lg') ?></span>
                <div>
                    <div class="stat-value"><?= e(money($finance['this_month'])) ?></div>
                    <div class="stat-label">Collected this month</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat tone-violet">
                <span class="stat-icon"><?= icon('file', 'ico-lg') ?></span>
                <div>
                    <div class="stat-value"><?= number_format((int) $stats['applicants']) ?></div>
                    <div class="stat-label">Applications in review</div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-8">
        <?php if ($trend !== []): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('chart') ?> Fee collection trend</div>
                <div class="card-body">
                    <canvas id="trendChart" height="90"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header">
                <?= icon('student') ?> Enrolment by programme
                <a class="ms-auto small fw-normal" href="<?= url('/reports/enrolment') ?>">Full report</a>
            </div>
            <div class="table-wrap">
                <table class="table mb-0">
                    <thead>
                    <tr><th>Code</th><th>Programme</th><th class="text-end">Students</th><th style="width:34%">Share</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $maxEnrol = 0;
                    foreach ($enrolment as $row) { $maxEnrol = max($maxEnrol, (int) $row['total']); }
                    ?>
                    <?php if ($enrolment === []): ?>
                        <tr><td colspan="4"><div class="empty-state"><?= icon('student') ?><p>No programmes recorded yet.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($enrolment as $row): ?>
                        <tr>
                            <td><code><?= e($row['code']) ?></code></td>
                            <td><?= e(str_limit($row['name'], 46)) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((int) $row['total']) ?></td>
                            <td>
                                <div class="meter">
                                    <span style="width:<?= $maxEnrol > 0 ? round(((int) $row['total'] / $maxEnrol) * 100) : 0 ?>%"></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($recentPayments !== []): ?>
            <div class="card">
                <div class="card-header">
                    <?= icon('money') ?> Recent payments
                    <a class="ms-auto small fw-normal" href="<?= url('/finance/payments') ?>">View all</a>
                </div>
                <div class="table-wrap">
                    <table class="table mb-0">
                        <thead><tr><th>Receipt</th><th>Student</th><th>Method</th><th class="text-end">Amount</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><code><?= e($payment['receipt_number']) ?></code></td>
                                <td>
                                    <div class="fw-semibold"><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></div>
                                    <div class="text-muted-sm"><?= e($payment['admission_number']) ?></div>
                                </td>
                                <td><?= status_badge($payment['method']) ?></td>
                                <td class="text-end fw-semibold"><?= e(money($payment['amount'])) ?></td>
                                <td class="text-muted-sm"><?= fdate($payment['paid_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <?php if ($applications !== []): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('file') ?> Admissions funnel</div>
                <div class="card-body">
                    <?php foreach (['submitted', 'under_review', 'shortlisted', 'accepted', 'enrolled', 'rejected'] as $stage): ?>
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span class="text-muted-sm"><?= e(humanize($stage)) ?></span>
                            <span class="badge text-bg-<?= status_class($stage) ?>"><?= (int) ($applications[$stage] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <a class="btn btn-sm btn-outline-primary w-100 mt-2" href="<?= url('/admissions/applications') ?>">Open admissions</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header">
                <?= icon('bell') ?> Announcements
                <a class="ms-auto small fw-normal" href="<?= url('/announcements') ?>">All</a>
            </div>
            <div class="card-body py-2">
                <?php if ($announcements === []): ?>
                    <p class="text-muted-sm mb-0 py-2">No announcements published.</p>
                <?php else: ?>
                    <ul class="list-clean">
                        <?php foreach ($announcements as $note): ?>
                            <li>
                                <a class="fw-semibold d-block" href="<?= url('/announcements/' . $note['id']) ?>">
                                    <?= e(str_limit($note['title'], 54)) ?>
                                </a>
                                <span class="text-muted-sm"><?= ago($note['published_at'] ?? $note['created_at']) ?>
                                    &middot; <?= status_badge($note['priority']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <?= icon('calendar') ?> Upcoming events
                <a class="ms-auto small fw-normal" href="<?= url('/events') ?>">Calendar</a>
            </div>
            <div class="card-body py-2">
                <?php if ($events === []): ?>
                    <p class="text-muted-sm mb-0 py-2">Nothing scheduled.</p>
                <?php else: ?>
                    <div class="timeline pt-2">
                        <?php foreach ($events as $event): ?>
                            <div class="timeline-item">
                                <div class="fw-semibold"><?= e($event['title']) ?></div>
                                <div class="text-muted-sm">
                                    <?= fdatetime($event['start_datetime']) ?>
                                    <?php if (!empty($event['venue'])): ?> &middot; <?= e($event['venue']) ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>

<?php if ($trend !== []): ?>
    <?php section('scripts'); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function () {
      var ctx = document.getElementById('trendChart');
      if (!ctx || typeof Chart === 'undefined') { return; }
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: <?= json_encode(array_column($trend, 'period')) ?>,
          datasets: [{
            label: 'Collected',
            data: <?= json_encode(array_map('floatval', array_column($trend, 'total'))) ?>,
            borderColor: '#1d4ed8',
            backgroundColor: 'rgba(29,78,216,.12)',
            fill: true, tension: .3, pointRadius: 3
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return v.toLocaleString(); } } } }
        }
      });
    })();
    </script>
    <?php endsection(); ?>
<?php endif; ?>
