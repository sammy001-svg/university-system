<?php
layout('layouts.app');
$pageTitle = 'Dashboard';
section('content');

$hour     = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$pct      = static fn (float $part, float $whole): float => $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
$ratio = $stats['academic'] > 0 ? (int) round($stats['students'] / $stats['academic']) : null;
?>

<!-- ============ Institutional header ============ -->
<section class="dash-hero">
    <div class="dash-hero-main">
        <div class="dash-eyebrow"><?= e($appName) ?> &middot; Management Information System</div>
        <h1><?= $greeting ?>, <?= e($currentUser['first_name'] ?? '') ?></h1>
        <p class="dash-meta">
            <?= date('l, j F Y') ?>
            <?php if ($semester !== null): ?>
                &middot; Academic Year <strong><?= e($semester['academic_year']) ?></strong> &middot; <?= e($semester['name']) ?>
            <?php endif; ?>
        </p>
    </div>
    <?php if ($calendar !== null): ?>
        <div class="dash-term">
            <div class="d-flex justify-content-between align-items-baseline gap-3">
                <span class="dash-term-phase"><?= e($calendar['phase']) ?></span>
                <span class="dash-term-week"><?= $calendar['week'] > 0 ? 'Week ' . $calendar['week'] . ' of ' . $calendar['weeks'] : $calendar['weeks'] . ' weeks' ?></span>
            </div>
            <div class="dash-term-bar" role="progressbar" aria-label="Semester progress"
                 aria-valuenow="<?= $calendar['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                <span style="width:<?= $calendar['progress'] ?>%"></span>
            </div>
            <div class="dash-term-dates">
                <span><?= fdate($semester['start_date']) ?></span>
                <span><?= $calendar['daysLeft'] ?> days remaining</span>
                <span><?= fdate($semester['end_date']) ?></span>
            </div>
            <?php if (!empty($semester['registration_end']) || !empty($semester['exam_start'])): ?>
                <div class="dash-term-keys">
                    <?php if (!empty($semester['registration_end'])): ?>
                        <span>Registration <?= $calendar['registrationOpen'] ? 'closes' : 'closed' ?> <b><?= fdate($semester['registration_end']) ?></b></span>
                    <?php endif; ?>
                    <?php if (!empty($semester['exam_start'])): ?>
                        <span>Examinations <b><?= fdate($semester['exam_start']) ?></b></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="dash-term"><span class="dash-term-phase">No semester is currently active</span></div>
    <?php endif; ?>
</section>

<?php if ($attention !== []): ?>
    <div class="dash-attention">
        <span class="dash-attention-title"><?= icon('bell', 'ico-sm') ?> Needs attention</span>
        <?php foreach ($attention as $item): ?>
            <a class="dash-chip tone-<?= e($item['tone']) ?>" href="<?= url($item['url']) ?>"><?= e($item['label']) ?> &rarr;</a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ============ Key indicators ============ -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="kpi" href="<?= url('/students') ?>">
            <div class="kpi-head"><span>Student body</span><?= icon('student') ?></div>
            <div class="kpi-value"><?= number_format($stats['students']) ?></div>
            <div class="kpi-sub">
                <?php if ($registration !== null): ?>
                    <b><?= number_format($registration['enrolled']) ?></b> enrolled this semester &middot; <?= $registration['rate'] ?>%
                <?php else: ?>
                    Active students
                <?php endif; ?>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="kpi" href="<?= url('/staff') ?>">
            <div class="kpi-head"><span>Faculty &amp; staff</span><?= icon('users') ?></div>
            <div class="kpi-value"><?= number_format($stats['staff']) ?></div>
            <div class="kpi-sub">
                <b><?= number_format($stats['academic']) ?></b> academic
                <?php if ($ratio !== null): ?>&middot; student&ndash;faculty ratio <b><?= $ratio ?>:1</b><?php endif; ?>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="kpi" href="<?= url('/academics/programs') ?>">
            <div class="kpi-head"><span>Academic programmes</span><?= icon('book') ?></div>
            <div class="kpi-value"><?= number_format($stats['programs']) ?></div>
            <div class="kpi-sub">
                <b><?= $stats['colleges'] ?></b> colleges &middot; <b><?= $stats['departments'] ?></b> departments &middot; <b><?= number_format($stats['courses']) ?></b> courses
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <?php if ($finance !== null): $rate = $pct($finance['collected'], $finance['billed']); ?>
            <a class="kpi" href="<?= url('/finance/invoices') ?>">
                <div class="kpi-head"><span>Fee collection rate</span><?= icon('money') ?></div>
                <div class="kpi-value"><?= $rate ?><small>%</small></div>
                <div class="meter <?= $rate >= 75 ? 'ok' : ($rate >= 40 ? 'warn' : 'bad') ?> my-2"><span style="width:<?= min(100, $rate) ?>%"></span></div>
                <div class="kpi-sub"><b><?= e(money($finance['collected'])) ?></b> of <?= e(money($finance['billed'])) ?> billed</div>
            </a>
        <?php else: ?>
            <a class="kpi" href="<?= url('/admissions/applications') ?>">
                <div class="kpi-head"><span>Applications in review</span><?= icon('file') ?></div>
                <div class="kpi-value"><?= number_format($stats['applicants']) ?></div>
                <div class="kpi-sub"><?= number_format($stats['offerings']) ?> class sections this semester</div>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ============ Registration & student body ============ -->
<?php if ($registration !== null || $standing !== []): ?>
<div class="row g-3 mb-3">
    <?php if ($registration !== null): $r = $registration; ?>
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <?= icon('clipboard') ?> Semester registration
                    <a class="ms-auto small fw-normal" href="<?= url('/students') ?>">Student list</a>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-end gap-4 flex-wrap mb-3">
                        <div>
                            <div class="figure-lg"><?= $r['rate'] ?>%</div>
                            <div class="text-muted-sm">registration complete (1st installment paid)</div>
                        </div>
                        <div class="text-muted-sm ms-auto text-end"><?= number_format($r['total']) ?> active students</div>
                    </div>
                    <?php
                    $segments = [
                        ['key' => 'enrolled',    'label' => 'Enrolled',           'value' => $r['enrolled'],    'class' => 'seg-ok'],
                        ['key' => 'pending',     'label' => 'Awaiting payment',   'value' => $r['pending'],     'class' => 'seg-warn'],
                        ['key' => 'not_started', 'label' => 'Not yet registered', 'value' => $r['not_started'], 'class' => 'seg-none'],
                    ];
                    ?>
                    <div class="seg-bar" role="img" aria-label="<?= e(implode(', ', array_map(static fn ($s) => $s['label'] . ' ' . $s['value'], $segments))) ?>">
                        <?php foreach ($segments as $s): if ($s['value'] <= 0) { continue; } ?>
                            <span class="<?= $s['class'] ?>" style="flex:<?= $s['value'] ?>" title="<?= e($s['label'] . ': ' . number_format($s['value']) . ' (' . $pct($s['value'], $r['total']) . '%)') ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="seg-legend">
                        <?php foreach ($segments as $s): ?>
                            <div>
                                <span class="swatch <?= $s['class'] ?>"></span>
                                <span class="seg-label"><?= e($s['label']) ?></span>
                                <b><?= number_format($s['value']) ?></b>
                                <span class="text-muted-sm"><?= $pct($s['value'], $r['total']) ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($r['planned'] + $r['committed'] + $r['not_committed'] > 0): ?>
                        <div class="dash-subhead">Registrar's semester list</div>
                        <div class="row g-2">
                            <?php foreach (['planned' => 'Planned', 'committed' => 'Made commitment', 'not_committed' => 'No commitment'] as $key => $label): ?>
                                <div class="col-4">
                                    <a class="mini-stat" href="<?= url('/students?stage=' . $key) ?>">
                                        <b><?= number_format($r[$key]) ?></b><span><?= e($label) ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($standing !== []): ?>
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header"><?= icon('student') ?> Student body by class standing</div>
                <div class="card-body">
                    <?php $maxStanding = max(1, ...array_column($standing, 'total')); ?>
                    <?php foreach ($standing as $row): ?>
                        <div class="hbar">
                            <div class="hbar-label"><?= e($row['label']) ?><span><?= e($row['note']) ?></span></div>
                            <div class="hbar-track"><span style="width:<?= round($row['total'] / $maxStanding * 100) ?>%"></span></div>
                            <div class="hbar-value"><?= number_format($row['total']) ?></div>
                        </div>
                    <?php endforeach; ?>

                    <?php $female = $gender['female'] ?? 0; $male = $gender['male'] ?? 0; $known = $female + $male; ?>
                    <?php if ($known > 0): ?>
                        <div class="dash-subhead">Gender</div>
                        <div class="split">
                            <div><b><?= $pct($female, $known) ?>%</b> Female <span class="text-muted-sm">(<?= number_format($female) ?>)</span></div>
                            <div class="text-end"><b><?= $pct($male, $known) ?>%</b> Male <span class="text-muted-sm">(<?= number_format($male) ?>)</span></div>
                        </div>
                        <div class="split-bar" role="img" aria-label="Female <?= $female ?>, male <?= $male ?>">
                            <span class="split-a" style="flex:<?= max($female, 0.0001) ?>"></span>
                            <span class="split-b" style="flex:<?= max($male, 0.0001) ?>"></span>
                        </div>
                        <?php if (($gender['unspecified'] ?? 0) + ($gender['other'] ?? 0) > 0): ?>
                            <div class="text-muted-sm mt-1"><?= number_format(($gender['unspecified'] ?? 0) + ($gender['other'] ?? 0)) ?> not recorded</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ============ Colleges & institution ============ -->
<div class="row g-3 mb-3">
    <?php if ($colleges !== []): ?>
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <?= icon('building') ?> Enrolment by college
                    <a class="ms-auto small fw-normal" href="<?= url('/reports/enrolment') ?>">Enrolment report</a>
                </div>
                <div class="card-body">
                    <?php $maxCollege = max(1, ...array_map('intval', array_column($colleges, 'total'))); ?>
                    <?php foreach ($colleges as $row): ?>
                        <div class="hbar hbar-wide">
                            <div class="hbar-label" title="<?= e($row['name']) ?>"><?= e($row['name']) ?>
                                <span><?= (int) $row['programs'] ?> programme<?= (int) $row['programs'] === 1 ? '' : 's' ?></span></div>
                            <div class="hbar-track"><span style="width:<?= round((int) $row['total'] / $maxCollege * 100) ?>%"></span></div>
                            <div class="hbar-value"><?= number_format((int) $row['total']) ?>
                                <span><?= $pct((int) $row['total'], $stats['students']) ?>%</span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="<?= $colleges !== [] ? 'col-xl-5' : 'col-12' ?>">
        <div class="card h-100">
            <div class="card-header"><?= icon('shield') ?> Institution at a glance</div>
            <div class="card-body">
                <div class="glance">
                    <?php
                    $glance = [
                        ['Colleges', $stats['colleges'], '/academics/faculties'],
                        ['Departments', $stats['departments'], '/academics/departments'],
                        ['Programmes', $stats['programs'], '/academics/programs'],
                        ['Courses', $stats['courses'], '/academics/courses'],
                        ['Class sections', $stats['offerings'], '/academics/offerings'],
                        ['Applications in review', $stats['applicants'], '/admissions/applications'],
                        ['Library volumes', $stats['books'], '/library/books'],
                        ['Hostel beds', $stats['hostel_beds'], '/hostel/rooms'],
                    ];
                    foreach ($glance as [$label, $value, $href]): ?>
                        <a href="<?= url($href) ?>"><b><?= number_format((int) $value) ?></b><span><?= e($label) ?></span></a>
                    <?php endforeach; ?>
                </div>
                <?php if ($staffMix !== []): ?>
                    <div class="dash-subhead">Staff composition</div>
                    <?php $maxStaff = max(1, ...array_values($staffMix)); ?>
                    <?php foreach ($staffMix as $category => $total): ?>
                        <div class="hbar">
                            <div class="hbar-label"><?= e(ucfirst($category)) ?></div>
                            <div class="hbar-track"><span style="width:<?= round($total / $maxStaff * 100) ?>%"></span></div>
                            <div class="hbar-value"><?= number_format($total) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============ Finance ============ -->
<?php if ($finance !== null): ?>
<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <?= icon('chart') ?> Finance overview
                <a class="ms-auto small fw-normal" href="<?= url('/reports/finance') ?>">Finance report</a>
            </div>
            <div class="card-body">
                <div class="fin-figures">
                    <div><span>Billed</span><b><?= e(money($finance['billed'])) ?></b></div>
                    <div><span>Collected</span><b><?= e(money($finance['collected'])) ?></b></div>
                    <div><span>Outstanding</span><b><?= e(money($finance['outstanding'])) ?></b></div>
                    <div><span>This month</span><b><?= e(money($finance['this_month'])) ?></b></div>
                </div>
                <div class="dash-subhead mb-2">Fees collected per month, last 12 months</div>
                <?php if ($trend === []): ?>
                    <div class="empty-state py-4"><?= icon('chart') ?><p>No payments recorded in the last 12 months.</p></div>
                <?php else: ?>
                    <div style="height:240px"><canvas id="trendChart" aria-label="Fees collected per month" role="img"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <?= icon('money') ?> Recent payments
                <a class="ms-auto small fw-normal" href="<?= url('/finance/payments') ?>">View all</a>
            </div>
            <?php if ($recentPayments === []): ?>
                <div class="card-body"><div class="empty-state"><?= icon('money') ?><p>No payments yet.</p></div></div>
            <?php else: ?>
                <ul class="feed">
                    <?php foreach ($recentPayments as $payment): ?>
                        <li>
                            <div class="feed-main">
                                <div class="fw-semibold"><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></div>
                                <div class="text-muted-sm"><?= e($payment['admission_number']) ?> &middot; <?= e(humanize($payment['method'])) ?> &middot; <?= fdate($payment['paid_at']) ?></div>
                            </div>
                            <div class="feed-amount"><?= e(money($payment['amount'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============ Workspace ============ -->
<div class="row g-3">
    <div class="col-lg-4">
        <?php if ($actions !== []): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('check') ?> Quick actions</div>
                <div class="card-body">
                    <div class="quick-actions">
                        <?php foreach ($actions as $action): ?>
                            <a href="<?= url($action['url']) ?>"><?= icon($action['icon']) ?><span><?= e($action['label']) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($applications !== []): ?>
            <div class="card">
                <div class="card-header">
                    <?= icon('file') ?> Admissions pipeline
                    <a class="ms-auto small fw-normal" href="<?= url('/admissions/applications') ?>">Open</a>
                </div>
                <div class="card-body">
                    <?php $maxApp = max(1, ...array_values($applications)); ?>
                    <?php foreach (['submitted', 'under_review', 'shortlisted', 'accepted', 'enrolled', 'rejected'] as $stage): $n = (int) ($applications[$stage] ?? 0); ?>
                        <div class="hbar">
                            <div class="hbar-label"><?= e(humanize($stage)) ?></div>
                            <div class="hbar-track"><span style="width:<?= round($n / $maxApp * 100) ?>%"></span></div>
                            <div class="hbar-value"><?= number_format($n) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <?= icon('bell') ?> Announcements
                <a class="ms-auto small fw-normal" href="<?= url('/announcements') ?>">All</a>
            </div>
            <div class="card-body py-2">
                <?php if ($announcements === []): ?>
                    <div class="empty-state"><?= icon('bell') ?><p>No announcements published.</p></div>
                <?php else: ?>
                    <ul class="list-clean">
                        <?php foreach ($announcements as $note): ?>
                            <li>
                                <a class="fw-semibold d-block" href="<?= url('/announcements/' . $note['id']) ?>"><?= e(str_limit($note['title'], 60)) ?></a>
                                <span class="text-muted-sm"><?= ago($note['published_at'] ?? $note['created_at']) ?>
                                    &middot; <?= status_badge($note['priority']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <?= icon('calendar') ?> Academic calendar
                <a class="ms-auto small fw-normal" href="<?= url('/events') ?>">Calendar</a>
            </div>
            <div class="card-body py-2">
                <?php if ($events === []): ?>
                    <div class="empty-state"><?= icon('calendar') ?><p>No upcoming events.</p></div>
                <?php else: ?>
                    <ul class="list-clean">
                        <?php foreach ($events as $event): $t = strtotime($event['start_datetime']); ?>
                            <li class="d-flex gap-3 align-items-center">
                                <div class="date-tile"><b><?= date('j', $t) ?></b><span><?= date('M', $t) ?></span></div>
                                <div>
                                    <div class="fw-semibold"><?= e($event['title']) ?></div>
                                    <div class="text-muted-sm"><?= date('D, H:i', $t) ?><?php if (!empty($event['venue'])): ?> &middot; <?= e($event['venue']) ?><?php endif; ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
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
      var symbol = <?= json_encode(money(0) === '' ? '' : preg_replace('/[\d.,\s]+/', '', money(0))) ?>;
      var fmt = function (v) { return symbol + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 }); };
      var brand = getComputedStyle(document.documentElement).getPropertyValue('--sidebar-top').trim() || '#914098';
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: <?= json_encode(array_map(static fn (string $p): string => date('M Y', strtotime($p . '-01')), array_column($trend, 'period'))) ?>,
          datasets: [{
            label: 'Fees collected',
            data: <?= json_encode(array_map('floatval', array_column($trend, 'total'))) ?>,
            borderColor: brand, borderWidth: 2,
            backgroundColor: 'rgba(145, 64, 152, .10)',
            pointBackgroundColor: brand, pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
            fill: true, tension: .3
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: function (c) { return ' Collected: ' + fmt(c.parsed.y); } } }
          },
          scales: {
            x: { grid: { display: false }, ticks: { color: '#64748b' } },
            y: { beginAtZero: true, border: { display: false }, grid: { color: '#eef1f6' },
                 ticks: { color: '#64748b', callback: fmt, maxTicksLimit: 5 } }
          }
        }
      });
    })();
    </script>
    <?php endsection(); ?>
<?php endif; ?>
