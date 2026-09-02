<?php
/**
 * Outlook / mail compose helpers for temporary share sessions (join URL + 6-digit code).
 */

function itm_outlook_share_build_subject($itemLabel)
{
    $itemLabel = trim((string)$itemLabel);
    if ($itemLabel === '') {
        $itemLabel = 'item';
    }

    return 'Shared ' . $itemLabel;
}

function itm_outlook_share_build_body($itemLabel, $joinUrl, $shareCode)
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

function itm_outlook_share_build_mailto_url($subject, $body)
{
    return 'mailto:?subject=' . rawurlencode((string)$subject) . '&body=' . rawurlencode((string)$body);
}

function itm_outlook_share_build_web_compose_url($subject, $body)
{
    return 'https://outlook.office.com/mail/deeplink/compose?subject='
        . rawurlencode((string)$subject)
        . '&body='
        . rawurlencode((string)$body);
}
