<?php
use App\Models\FeeStructure;
use App\Services\Academic\DocumentService;

layout('layouts.document');
section('content');
$s       = $student;
$name    = (string) preg_replace('/\s+/', ' ', trim($s['first_name'] . ' ' . ($s['other_name'] ?? '') . ' ' . $s['last_name']));
$type    = $doc['doc_type'];
$revoked = $doc['status'] === 'revoked';
$he      = ['male' => 'he', 'female' => 'she'][$s['gender'] ?? ''] ?? 'they';
$crest   = static function () use ($appLogo, $appShortName): string {
    return !empty($appLogo) ? '<img src="' . e(uploaded($appLogo)) . '" alt="">' : e(substr((string) $appShortName, 0, 3));
};
$letterhead = static function () use ($appName, $crest): string {
    ob_start(); ?>
    <div class="letterhead">
        <div class="crest"><?= $crest() ?></div>
        <div class="flex-grow-1">
            <h1><?= e($appName) ?></h1>
            <div class="motto"><?= e((string) setting('institution_motto', '')) ?></div>
            <div class="contact"><?= e(implode(' · ', array_filter([(string) setting('institution_address', ''), (string) setting('institution_phone', ''),
                (string) setting('institution_email', ''), (string) setting('institution_website', '')]))) ?></div>
        </div>
    </div>
    <?php return (string) ob_get_clean();
};
$verify = static function (int $size = 96) use ($verifyUrl, $doc): string {
    ob_start(); ?>
    <div class="verify-box">
        <div class="qr" data-qr="<?= e($verifyUrl) ?>" style="width:<?= $size ?>px;height:<?= $size ?>px"></div>
        <div>Scan to verify, or visit<br><b><?= e(url('/verify')) ?></b><br>and enter code <b style="font-size:13px;letter-spacing:1px"><?= e($doc['verification_code']) ?></b>
            <br>Serial <?= e($doc['serial']) ?></div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="toolbar text-center py-3">
    <button class="btn btn-primary btn-sm" onclick="window.print()">Print / save as PDF</button>
    <button class="btn btn-light btn-sm" onclick="history.back()">Back</button>
    <?php if ($revoked): ?><span class="badge text-bg-danger ms-2">Revoked: <?= e((string) $doc['revoked_reason']) ?></span><?php endif; ?>
</div>

<?php if ($type === 'id_card'): ?>
    <div class="sheet id-sheet">
        <?php if ($revoked): ?><div class="stamp-revoked">REVOKED</div><?php endif; ?>
        <div class="id-card">
            <div class="id-band"><div class="crest"><?= $crest() ?></div><div><b><?= e($appName) ?></b><span>Student identity card</span></div></div>
            <div class="id-body">
                <div class="id-photo"><?php if (!empty($s['avatar'])): ?><img src="<?= e(uploaded($s['avatar'])) ?>" alt=""><?php else: ?><?= e(initials($s['first_name'], $s['last_name'])) ?><?php endif; ?></div>
                <div class="id-fields">
                    <div class="name"><?= e($name) ?></div>
                    <div class="k">Student ID</div><div><b><?= e($s['admission_number']) ?></b></div>
                    <div class="k">Programme</div><div><?= e(str_limit($s['program_name'], 46)) ?></div>
                    <div class="k">Valid until</div><div><b><?= e(fdate($doc['valid_until'])) ?></b></div>
                </div>
            </div>
            <div class="id-foot"></div>
        </div>
        <div class="id-card">
            <div class="id-back">
                <div class="d-flex gap-2 align-items-start">
                    <div class="qr" data-qr="<?= e($verifyUrl) ?>" style="width:22mm;height:22mm;flex:0 0 auto"></div>
                    <div>This card is the property of <?= e($appName) ?> and must be shown on request. If found, please return it to the
                        Office of Admission and Records<?= setting('institution_phone') ? ' or call ' . e((string) setting('institution_phone')) : '' ?>.
                        <br><br>Verify: <?= e(url('/verify')) ?><br>Code <b><?= e($doc['verification_code']) ?></b> &middot; <?= e($doc['serial']) ?></div>
                </div>
            </div>
            <div class="id-foot"></div>
        </div>
    </div>
<?php else: ?>
    <div class="sheet">
        <?php if ($revoked): ?><div class="stamp-revoked">REVOKED</div><?php endif; ?>
        <?= $letterhead() ?>
        <div class="meta-line mt-3"><span>Ref: <?= e($doc['serial']) ?></span><span><?= e(date('j F Y', strtotime($doc['issued_at']))) ?></span></div>
        <div class="doc-title"><?= e(DocumentService::TYPES[$type]) ?></div>

        <?php if ($type === 'transcript'): ?>
            <?php partial('partials.transcript-facts', ['student' => $s, 'tx' => $tx, 'printedOn' => $doc['issued_at']]) ?>
            <div style="height:10px"></div>
            <?php partial('partials.transcript-table', ['tx' => $tx]) ?>
            <table class="grid-box" style="margin-top:10px">
                <tr>
                    <td style="width:34%"><span class="k">Credit hours earned:</span> <b><?= e((string) ($summary['Credits earned'] ?? (int) $tx['credits'])) ?></b></td>
                    <td style="width:33%"><span class="k">Cumulative GPA:</span> <b><?= e((string) ($summary['CGPA'] ?? number_format((float) $tx['cgpa'], 2))) ?></b></td>
                    <td style="width:33%"><span class="k">Status:</span> <b><?= e(humanize((string) $s['status'])) ?></b></td>
                </tr>
            </table>
            <p class="mt-2" style="font-family:system-ui;font-size:11px">This transcript is official only with a valid verification code.</p>
        <?php else: ?>
            <p class="body-text">To whom it may concern,</p>
            <?php if ($type === 'enrollment_letter'): ?>
                <p class="body-text">This is to certify that <b><?= e($name) ?></b> (Student ID <b><?= e($s['admission_number']) ?></b>) is a duly registered,
                    full-time student of <?= e($appName) ?>, enrolled for the <b><?= e((string) ($summary['Enrolled for'] ?? '')) ?></b> in the
                    <?= e($s['program_name']) ?> programme of the <?= e($s['faculty_name']) ?>, with the class standing of
                    <b><?= e((string) ($summary['Class standing'] ?? FeeStructure::classStanding($s))) ?></b>.</p>
                <p class="body-text">This letter is issued at the request of the student for whatever purpose it may serve, and is valid until
                    <?= e(fdate($doc['valid_until'])) ?>.</p>
            <?php elseif ($type === 'admission_letter'): ?>
                <p class="body-text">This is to confirm that <b><?= e($name) ?></b> was admitted to <?= e($appName) ?> on
                    <?= e((string) ($summary['Admitted'] ?? '')) ?> to pursue the <b><?= e($s['program_name']) ?></b> programme in the
                    <?= e($s['faculty_name']) ?>, under Student ID <b><?= e($s['admission_number']) ?></b>.</p>
                <p class="body-text">We congratulate <?= $he === 'they' ? 'the student' : ($he === 'he' ? 'him' : 'her') ?> on this admission.</p>
            <?php elseif ($type === 'clearance_letter'): ?>
                <p class="body-text">This is to certify that <b><?= e($name) ?></b> (Student ID <b><?= e($s['admission_number']) ?></b>) of the
                    <?= e($s['program_name']) ?> programme has been cleared by every unit of the university, including Finance, the Library and the
                    Office of Admission and Records, and has no outstanding obligations to <?= e($appName) ?>.</p>
            <?php endif; ?>
            <table class="facts">
                <?php foreach ($summary as $k => $v): ?><tr><th><?= e((string) $k) ?></th><td><?= e((string) $v) ?></td></tr><?php endforeach; ?>
            </table>
        <?php endif; ?>

        <div class="signature">
            <div>
                <div style="height:46px"></div>
                <div class="line"><?= e($signatory ?? 'Registrar') ?><br><span style="font-size:12px;color:#666">Office of Admission and Records</span></div>
            </div>
            <?= $verify() ?>
        </div>
        <div class="footer-note">Any alteration makes this document invalid. Confirm it is genuine at <?= e(url('/verify')) ?> using code <?= e($doc['verification_code']) ?>.</div>
    </div>
<?php endif; ?>
<?php endsection(); ?>
