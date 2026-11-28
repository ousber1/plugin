<?php $company = (string) setting('company_name', 'BERRADI WALL ART'); ?>
<!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= e(is_rtl() ? 'rtl' : 'ltr') ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= e($subject ?? $company) ?></title></head>
<body style="margin:0;padding:0;background:#F8F7F4;font-family:Arial,Helvetica,sans-serif;color:#292929">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F7F4;padding:32px 12px">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border:1px solid #E6E1D7">
            <tr><td style="background:#171717;padding:26px 32px;text-align:center">
                <div style="font-family:Georgia,serif;color:#FFFFFF;font-size:24px;letter-spacing:4px">BERRADI</div>
                <div style="color:#C6A66B;font-size:10px;letter-spacing:6px;margin-top:4px">WALL ART</div>
            </td></tr>
            <tr><td style="padding:36px 32px;font-size:15px;line-height:1.6;text-align:<?= is_rtl() ? 'right' : 'left' ?>">
                <?= $this->section('content') ?>
            </td></tr>
            <tr><td style="padding:20px 32px;border-top:1px solid #E6E1D7;font-size:12px;color:#6F6A62;text-align:center">
                <?= e($company) ?><?php if (setting('company_phone')): ?> · <?= e(setting('company_phone')) ?><?php endif; ?><?php if (setting('company_email')): ?> · <?= e(setting('company_email')) ?><?php endif; ?>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
