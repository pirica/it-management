<?php
/**
 * Shared QA step result display (runner JSON → markdown/XLSX).
 *
 * Why: Pass steps with N/A or Skip notes are not real successes; reports must show N/A, not OK.
 */
declare(strict_types=1);

function mbqa_step_note_is_na_or_skip(string $note): bool
{
    $note = trim($note);

    return $note !== '' && preg_match('/^(?:Skip|N\/A)\b/i', $note) === 1;
}

function mbqa_step_human_result(string $status, string $note = ''): string
{
    if ($status === 'Pass' && mbqa_step_note_is_na_or_skip($note)) {
        return 'N/A';
    }
    if ($status === 'Pass') {
        return 'OK';
    }
    if ($status === 'Fail') {
        return 'Failed';
    }

    return $status;
}

/**
 * Pass step recorded because the check does not apply (not a real OK).
 */
function mbqa_step_na_note(string $reason): string
{
    $reason = trim($reason);
    if ($reason === '') {
        return 'N/A';
    }
    if (mbqa_step_note_is_na_or_skip($reason)) {
        return $reason;
    }

    return 'N/A (' . $reason . ')';
}
