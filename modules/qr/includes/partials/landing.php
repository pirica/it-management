<?php
/**
 * Public landing pages for dynamic QR codes.
 */
$row = $qrLandingRow ?? [];
$p = $qrLandingPayload ?? [];
$token = trim((string) ($row['access_token'] ?? ''));
$companyId = (int) ($row['company_id'] ?? 0);
$type = (string) ($row['type_slug'] ?? '');
?>
<div class="qr-landing card" style="max-width:640px;margin:24px auto;padding:24px;">
<?php if ($type === 'website'): ?>
    <h1><?= sanitize((string)($p['title'] ?? 'Website')) ?></h1>
    <p><a class="btn btn-primary" href="<?= sanitize((string)($p['url'] ?? '#')) ?>" rel="noopener">Open link</a></p>
<?php elseif ($type === 'pdf' || $type === 'mp3'): ?>
    <h1><?= sanitize((string)($p['title'] ?? ucfirst($type))) ?></h1>
    <?php $fileUrl = itm_qr_generator_asset_serve_url($companyId, (string)($p['file_path'] ?? ''), $token); ?>
    <?php if ($fileUrl): ?>
        <?php if ($type === 'pdf'): ?><p><a class="btn btn-primary" href="<?= sanitize($fileUrl) ?>" target="_blank" rel="noopener">View PDF</a></p><?php else: ?><audio controls src="<?= sanitize($fileUrl) ?>"></audio><?php endif; ?>
    <?php endif; ?>
<?php elseif ($type === 'images'): ?>
    <h1><?= sanitize((string)($p['title'] ?? 'Gallery')) ?></h1>
    <div style="display:flex;flex-wrap:wrap;gap:12px;"><?php foreach ((array)($p['files'] ?? []) as $fp): $u = itm_qr_generator_asset_serve_url($companyId, (string)$fp, $token); if ($u): ?><img src="<?= sanitize($u) ?>" alt="" style="max-width:200px;border-radius:6px;"><?php endif; endforeach; ?></div>
<?php elseif ($type === 'video'): ?>
    <h1><?= sanitize((string)($p['title'] ?? 'Video')) ?></h1>
    <?php if (($p['url'] ?? '') !== ''): ?><p><a class="btn btn-primary" href="<?= sanitize((string)$p['url']) ?>" target="_blank" rel="noopener">Watch</a></p><?php endif; ?>
    <?php $vu = itm_qr_generator_asset_serve_url($companyId, (string)($p['file_path'] ?? ''), $token); if ($vu): ?><video controls src="<?= sanitize($vu) ?>" style="max-width:100%;"></video><?php endif; ?>
<?php elseif ($type === 'apps'): ?>
    <h1><?= sanitize((string)($p['title'] ?? 'Download app')) ?></h1>
    <?php if (($p['ios_url'] ?? '') !== ''): ?><p><a class="btn" href="<?= sanitize((string)$p['ios_url']) ?>">App Store</a></p><?php endif; ?>
    <?php if (($p['android_url'] ?? '') !== ''): ?><p><a class="btn" href="<?= sanitize((string)$p['android_url']) ?>">Google Play</a></p><?php endif; ?>
<?php elseif ($type === 'list_of_links' || $type === 'social'): ?>
    <h1><?= sanitize((string)($p['title'] ?? 'Links')) ?></h1>
    <ul><?php foreach ((array)($p['links'] ?? []) as $link): if (trim((string)($link['url'] ?? '')) === '') continue; ?><li><a class="itm-plain-link" href="<?= sanitize((string)$link['url']) ?>"><?= sanitize((string)($link['label'] ?? $link['url'])) ?></a></li><?php endforeach; ?></ul>
<?php elseif ($type === 'menu'): ?>
    <h1><?= sanitize((string)($p['title'] ?? 'Menu')) ?></h1>
    <?php foreach ((array)($p['sections'] ?? []) as $section): ?>
        <h3><?= sanitize((string)($section['name'] ?? '')) ?></h3>
        <ul><?php foreach ((array)($section['items'] ?? []) as $item): ?><li><?= sanitize((string)($item['name'] ?? '')) ?> — <?= sanitize((string)($item['price'] ?? '')) ?></li><?php endforeach; ?></ul>
    <?php endforeach; ?>
<?php elseif ($type === 'business' || $type === 'vcard'): ?>
    <h1><?= sanitize((string)($p['name'] ?? trim((string)($p['first_name'] ?? '') . ' ' . (string)($p['last_name'] ?? '')))) ?></h1>
    <?php if (($p['description'] ?? '') !== ''): ?><p><?= sanitize((string)$p['description']) ?></p><?php endif; ?>
    <?php if (($p['phone'] ?? '') !== ''): ?><p><a class="itm-plain-link" href="tel:<?= sanitize(preg_replace('/[^\d+]/', '', (string)$p['phone'])) ?>"><?= sanitize((string)$p['phone']) ?></a></p><?php endif; ?>
    <?php if (($p['email'] ?? '') !== ''): ?><p><a class="itm-plain-link" href="mailto:<?= sanitize((string)$p['email']) ?>"><?= sanitize((string)$p['email']) ?></a></p><?php endif; ?>
    <?php if (($p['website'] ?? '') !== ''): ?><p><a class="itm-plain-link" href="<?= sanitize((string)$p['website']) ?>"><?= sanitize((string)$p['website']) ?></a></p><?php endif; ?>
    <?php if (($p['address'] ?? '') !== ''): ?><p><?= sanitize((string)$p['address']) ?></p><?php endif; ?>
<?php elseif ($type === 'coupon'): ?>
    <h1><?= sanitize((string)($p['title'] ?? 'Coupon')) ?></h1>
    <p style="font-size:1.5em;font-weight:bold;"><?= sanitize((string)($p['code'] ?? '')) ?></p>
    <p><?= sanitize((string)($p['description'] ?? '')) ?></p>
    <?php if (($p['expires'] ?? '') !== ''): ?><p>Expires: <?= sanitize((string)$p['expires']) ?></p><?php endif; ?>
<?php elseif ($type === 'email'): ?>
    <h1>Email</h1>
    <p><a class="btn btn-primary" href="mailto:<?= sanitize((string)($p['to'] ?? '')) ?>?subject=<?= rawurlencode((string)($p['subject'] ?? '')) ?>&body=<?= rawurlencode((string)($p['body'] ?? '')) ?>">Send email</a></p>
<?php elseif ($type === 'phone'): ?>
    <h1>Call</h1>
    <p><a class="btn btn-primary" href="tel:<?= sanitize(preg_replace('/[^\d+]/', '', (string)($p['number'] ?? ''))) ?>">Call now</a></p>
<?php else: ?>
    <p>Content not available.</p>
<?php endif; ?>
</div>
