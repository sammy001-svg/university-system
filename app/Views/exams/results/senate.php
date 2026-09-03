<?php layout('layouts.app'); section('content'); ?>
<div class="page-head">
    <div>
        <h1>Senate Graduation &amp; Results List</h1>
        <p class="lede">Institutional senate board summary report.</p>
    </div>
    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= icon('download', 'ico-sm') ?> Print Senate List</button>
</div>

<div class="card p-4">
    <div class="text-center mb-4">
        <h3 class="fw-bold mb-1"><?= e($appName) ?></h3>
        <h5>Senate Board of Examiners Report</h5>
    </div>
    <div class="alert alert-info">Senate result recommendation lists compiled per department and faculty.</div>
</div>
<?php endsection(); ?>
