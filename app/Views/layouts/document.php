<?php
/** Layout for printable official documents: letterhead page, no navigation. */
use App\Core\View;

$pageTitle = $pageTitle ?? 'Document';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> &middot; <?= e($appName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --brand: #914098; --brand-dark: #6f2f76; --gold: #c9a227; --ink: #1f1724; }
        body { background: #ece7ee; color: var(--ink); font-family: "Georgia", "Times New Roman", serif; }
        .toolbar { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .sheet { width: 210mm; min-height: 297mm; margin: 18px auto; background: #fff; padding: 18mm 18mm 22mm; position: relative;
                 box-shadow: 0 6px 30px rgba(0,0,0,.12); }
        .letterhead { display: flex; align-items: center; gap: 16px; padding-bottom: 12px; border-bottom: 3px double var(--brand); }
        .crest { width: 64px; height: 64px; border-radius: 12px; background: var(--brand); color: #fff; display: grid; place-items: center;
                 font-weight: 700; font-size: 22px; flex: 0 0 auto; }
        .crest img { width: 64px; height: 64px; object-fit: contain; }
        .letterhead h1 { font-size: 22px; margin: 0; color: var(--brand-dark); letter-spacing: .3px; }
        .letterhead .motto { font-style: italic; font-size: 12.5px; color: #6b5f70; }
        .letterhead .contact { font-family: system-ui, sans-serif; font-size: 11px; color: #6b5f70; }
        .doc-title { text-align: center; margin: 26px 0 18px; font-size: 19px; letter-spacing: 2px; text-transform: uppercase; color: var(--brand-dark); }
        .meta-line { display: flex; justify-content: space-between; font-family: system-ui, sans-serif; font-size: 12px; color: #555; }
        .body-text { font-size: 15px; line-height: 1.75; text-align: justify; }
        .facts { width: 100%; font-size: 14px; margin: 14px 0; }
        .facts th { width: 34%; font-weight: normal; color: #6b5f70; padding: 4px 0; }
        .facts td { font-weight: bold; padding: 4px 0; }
        .signature { margin-top: 48px; display: flex; justify-content: space-between; align-items: flex-end; }
        .signature .line { border-top: 1px solid #333; width: 240px; padding-top: 4px; font-size: 13px; }
        .verify-box { display: flex; gap: 12px; align-items: center; font-family: system-ui, sans-serif; font-size: 10.5px; color: #555; }
        .verify-box .qr { width: 96px; height: 96px; }
        .stamp-revoked { position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-24deg); font-size: 64px; font-weight: 700;
                         color: rgba(185, 28, 28, .28); border: 8px solid rgba(185, 28, 28, .28); padding: 8px 26px; font-family: system-ui, sans-serif; }
        table.transcript { width: 100%; font-family: system-ui, sans-serif; font-size: 11.5px; border-collapse: collapse; }
        table.transcript th { background: #f5eef6; color: var(--brand-dark); text-align: left; padding: 4px 6px; font-weight: 600; }
        table.transcript td { padding: 3px 6px; border-bottom: 1px solid #eee; }
        .term-head { font-family: system-ui, sans-serif; font-weight: 700; font-size: 12.5px; margin: 12px 0 4px; color: var(--brand-dark); }
        .footer-note { position: absolute; bottom: 10mm; left: 18mm; right: 18mm; font-family: system-ui, sans-serif; font-size: 9.5px; color: #888;
                       border-top: 1px solid #eee; padding-top: 6px; text-align: center; }
        /* Office of Admission and Records forms: the semester grade report and the transcript. */
        .form-head { text-align: center; position: relative; padding-bottom: 6px; }
        .form-head .crest { position: absolute; left: 0; top: 0; }
        .form-head h1 { font-size: 21px; margin: 0; letter-spacing: .2px; }
        .form-head .office { font-size: 13px; }
        .form-head .addr { font-family: system-ui, sans-serif; font-size: 10.5px; color: #333; line-height: 1.45; }
        .form-head .strip { margin-top: 6px; padding: 3px 8px; background: #f2ecf3; border-top: 1px solid #000; border-bottom: 1px solid #000;
                            font-family: system-ui, sans-serif; font-size: 10.5px; display: flex; justify-content: center; gap: 22px; }
        .office-line { text-align: center; font-weight: 700; font-size: 13px; letter-spacing: .4px; margin-top: 12px; }
        .record-title { text-align: center; font-weight: 700; font-size: 15px; letter-spacing: 1.2px; margin: 8px 0 2px; text-decoration: underline; }
        .grid-box { width: 100%; border-collapse: collapse; margin-top: 14px; font-family: system-ui, sans-serif; font-size: 12.5px; }
        .grid-box td { border: 1px solid #000; padding: 5px 8px; vertical-align: top; }
        .grid-box .k { color: #333; }
        .grid-box b { font-weight: 700; }
        .rec-table { width: 100%; border-collapse: collapse; margin-top: 16px; font-family: system-ui, sans-serif; font-size: 12.5px; }
        .rec-table th, .rec-table td { border: 1px solid #000; padding: 5px 8px; }
        .rec-table thead th { text-align: center; font-weight: 700; background: #fff; }
        .rec-table td.c { text-align: center; }
        .rec-table td.r { text-align: right; }
        .rec-table tr.sum td { font-weight: 700; }
        .sig-lines { margin-top: 46px; font-family: system-ui, sans-serif; font-size: 12.5px; }
        .sig-lines .line { display: inline-block; border-bottom: 1px solid #000; min-width: 150px; margin: 0 14px 18px 4px; }
        .tx-table { width: 100%; border-collapse: collapse; font-family: system-ui, sans-serif; font-size: 11.5px; }
        .tx-table th, .tx-table td { border: 1px solid #000; padding: 3px 6px; vertical-align: top; }
        .tx-table thead th { text-align: center; font-weight: 700; letter-spacing: .3px; }
        .tx-table td.c { text-align: center; }
        .tx-table td.term { font-weight: 700; width: 86px; }
        .tx-table tr.gpa td { font-weight: 700; }
        .tx-table tr.gpa td.label { text-align: right; }
        .doc-foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; margin-top: 22px;
                    font-family: system-ui, sans-serif; font-size: 10.5px; color: #444; }
        .doc-foot .motto { font-style: italic; }

        /* ID card: CR80 (85.6 x 54 mm) */
        .id-sheet { min-height: auto; padding: 14mm; }
        .id-card { width: 85.6mm; height: 54mm; border-radius: 3.2mm; overflow: hidden; position: relative; font-family: system-ui, sans-serif;
                   box-shadow: 0 0 0 .3mm #ccc; display: inline-block; margin: 0 6mm 6mm 0; vertical-align: top; background: #fff; }
        .id-band { background: linear-gradient(120deg, var(--brand-dark), var(--brand)); color: #fff; padding: 2mm 3mm; display: flex; align-items: center; gap: 2mm; }
        .id-band .crest { width: 8mm; height: 8mm; border-radius: 1.5mm; font-size: 3mm; background: var(--gold); color: #3b1440; }
        .id-band .crest img { width: 8mm; height: 8mm; }
        .id-band b { font-size: 2.9mm; line-height: 1.1; display: block; }
        .id-band span { font-size: 2.1mm; opacity: .85; letter-spacing: .3mm; text-transform: uppercase; }
        .id-body { display: flex; gap: 3mm; padding: 2.5mm 3mm; }
        .id-photo { width: 20mm; height: 25mm; border-radius: 1.5mm; background: #f1e9f2; display: grid; place-items: center; font-size: 7mm; font-weight: 700; color: var(--brand); overflow: hidden; }
        .id-photo img { width: 100%; height: 100%; object-fit: cover; }
        .id-fields { font-size: 2.4mm; line-height: 1.35; flex: 1; }
        .id-fields .name { font-size: 3.3mm; font-weight: 700; margin-bottom: .8mm; }
        .id-fields .k { color: #776a7c; font-size: 1.9mm; text-transform: uppercase; letter-spacing: .2mm; }
        .id-foot { position: absolute; bottom: 0; left: 0; right: 0; background: var(--gold); height: 2mm; }
        .id-back { padding: 4mm; font-size: 2.2mm; line-height: 1.4; color: #444; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
<?= View::section('content') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll('[data-qr]').forEach(function (el) {
  if (typeof QRCode === 'undefined') { el.textContent = el.dataset.qr; return; }
  new QRCode(el, { text: el.dataset.qr, width: el.offsetWidth || 96, height: el.offsetWidth || 96, correctLevel: QRCode.CorrectLevel.M });
});
</script>
</body>
</html>
