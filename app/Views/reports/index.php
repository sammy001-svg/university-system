<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div><h1>Reports</h1><p class="lede">Institutional analytics and downloadable reports.</p></div>
</div>

<div class="row g-3">
    <?php $reports = [
        ['Enrolment Report', 'Student enrolment by programme, faculty and gender.', '/reports/enrolment', 'student'],
        ['Finance Report', 'Revenue, collections and outstanding balances.', '/reports/finance', 'finance'],
        ['Academic Report', 'Grade distributions and pass rates.', '/reports/academic', 'results'],
        ['Attendance Report', 'Course-level attendance rates.', '/reports/attendance', 'attendance'],
    ]; ?>
    <?php foreach ($reports as [$title, $desc, $url, $iconName]): ?>
    <div class="col-md-6 col-xl-3">
        <a href="<?= url($url) ?>" class="card text-decoration-none h-100 card-hover">
            <div class="card-body text-center p-4">
                <div class="mb-3"><?= icon($iconName, 'ico-xl') ?></div>
                <h6 class="mb-1"><?= e($title) ?></h6>
                <p class="text-muted-sm mb-0"><?= e($desc) ?></p>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endsection(); ?>
