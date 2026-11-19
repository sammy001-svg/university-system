<?php
/** The printed letterhead of the records office: crest, university name, address and contact strip. */
$address = (string) setting('institution_address', '');
$phone   = (string) setting('institution_phone', '');
$web     = (string) setting('institution_website', '');
$mail    = (string) setting('institution_email', '');
?>
<div class="form-head">
    <div class="crest"><?= !empty($appLogo) ? '<img src="' . e(uploaded($appLogo)) . '" alt="">' : e(substr((string) $appShortName, 0, 3)) ?></div>
    <h1><?= e($appName) ?><?= $appShortName && $appShortName !== $appName ? ' (' . e((string) $appShortName) . ')' : '' ?></h1>
    <?php if (empty($compact) && ($address !== '' || $phone !== '')): ?>
        <div class="addr">
            <?php if ($address !== ''): ?><?= nl2br(e($address)) ?><?php endif; ?>
            <?php if ($phone !== ''): ?><br>Tel: <?= e($phone) ?><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (empty($compact) && ($web !== '' || $mail !== '')): ?>
        <div class="strip">
            <?php if ($web !== ''): ?><span>Website: <?= e($web) ?></span><?php endif; ?>
            <?php if ($mail !== ''): ?><span>Email: <?= e($mail) ?></span><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($office)): ?><div class="office-line"><?= e($office) ?></div><?php endif; ?>
</div>
