<?php
use App\Services\Academic\DocumentService;

layout('layouts.app');
section('content');
$s = $student;
?>
<div class="page-head">
    <div>
        <h1><?= $self ? 'My documents' : 'Official documents' ?></h1>
        <p class="lede"><?= e(trim($s['first_name'] . ' ' . $s['last_name'])) ?> &middot; <?= e($s['admission_number']) ?> &middot; <?= e($s['program_name']) ?></p>
    </div>
    <a class="btn btn-sm btn-light" href="<?= url($self ? '/portal' : '/students/' . $s['id']) ?>">&larr; Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= icon('file') ?> Issued documents <span class="badge text-bg-light"><?= count($documents) ?></span></div>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Document</th><th>Serial</th><th>Issued</th><th>Status</th><th class="text-end">Checked</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($documents === []): ?>
                        <tr><td colspan="6"><div class="empty-state"><?= icon('file') ?><p>No documents issued yet.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($documents as $d): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(DocumentService::TYPES[$d['doc_type']]) ?></td>
                            <td><code><?= e($d['serial']) ?></code></td>
                            <td class="text-muted-sm"><?= fdate($d['issued_at']) ?><?= $d['issued_by_name'] ? '<br>' . e($d['issued_by_name']) : '' ?></td>
                            <td><?php if ($d['status'] === 'revoked'): ?><span class="badge text-bg-danger">Revoked</span><div class="text-muted-sm"><?= e((string) $d['revoked_reason']) ?></div>
                                <?php elseif ($d['valid_until'] && $d['valid_until'] < date('Y-m-d')): ?><span class="badge text-bg-secondary">Expired</span>
                                <?php else: ?><span class="badge text-bg-success">Valid</span><?= $d['valid_until'] ? '<div class="text-muted-sm">until ' . fdate($d['valid_until']) . '</div>' : '' ?><?php endif; ?></td>
                            <td class="text-end text-muted-sm"><?= (int) $d['verify_count'] ?>&times;</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-light" target="_blank" href="<?= url('/issued-documents/' . $d['id']) ?>"><?= icon('print', 'ico-sm') ?> Print</a>
                                <?php if (!$self && $canIssue && $d['status'] === 'valid'): ?>
                                    <button class="btn btn-sm btn-light text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#rv<?= $d['id'] ?>">Revoke</button>
                                    <form class="collapse mt-2" id="rv<?= $d['id'] ?>" method="post" action="<?= url('/issued-documents/' . $d['id'] . '/revoke') ?>">
                                        <?= csrf_field() ?>
                                        <div class="input-group input-group-sm"><input class="form-control" name="reason" placeholder="Reason" required><button class="btn btn-outline-danger">Revoke</button></div>
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
    <div class="col-lg-4">
        <?php if ($canIssue): ?>
            <div class="card mb-3">
                <div class="card-header"><?= icon('plus') ?> <?= $self ? 'Get an enrolment letter' : 'Issue a document' ?></div>
                <div class="card-body">
                    <form method="post" action="<?= url($self ? '/portal/documents' : '/students/' . $s['id'] . '/documents') ?>">
                        <?= csrf_field() ?>
                        <?php if ($self): ?>
                            <p class="small">An official letter confirming you are enrolled this semester, e.g. for a visa, sponsor or employer.
                                Available once your registration is complete.</p>
                        <?php else: ?>
                            <select class="form-select mb-2" name="doc_type"><?= options($types) ?></select>
                        <?php endif; ?>
                        <button class="btn btn-primary w-100"><?= $self ? 'Generate my letter' : 'Issue' ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body small">
                <b>How verification works.</b> Every document carries a QR code and a code. Anyone - an embassy, employer or another
                university - can scan it or enter the code at <a href="<?= url('/verify') ?>"><?= e(url('/verify')) ?></a> to confirm it is genuine.
            </div>
        </div>
    </div>
</div>
<?php endsection(); ?>
