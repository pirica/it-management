<?php
/**
 * WhatsApp deep-link helpers for temporary share sessions (join URL + 6-digit code).
 */

function itm_whatsapp_share_build_message($itemLabel, $joinUrl, $shareCode)
{
    $itemLabel = trim((string)$itemLabel);
    $joinUrl = trim((string)$joinUrl);
    $shareCode = trim((string)$shareCode);
    if ($itemLabel === '') {
        $itemLabel = 'item';
    }

    $lines = [
        'View shared ' . $itemLabel . ':',
        $joinUrl,
    ];
    if ($shareCode !== '') {
        $lines[] = 'Code: ' . $shareCode;
    }
    $lines[] = '(Link expires in 30 minutes.)';

    return implode("\n", $lines);
}

function itm_whatsapp_share_build_url($message)
{
    return 'https://wa.me/?text=' . rawurlencode((string)$message);
}
