<?php
/**
 * Canonical signatures for rows created by module_browser_qa_runner (and reserved import markers).
 *
 * Why: employee_companies must not treat every username starting with "mbqa-" as disposable test data;
 * only values matching the runner's MBQA-{table}-{company}-{seq}-{hash} tag bypass Admin delete guards.
 */

if (!function_exists('itm_mbqa_runner_row_tag')) {
    /**
     * Same tag shape as mbqa_build_random_insert_row() in module_browser_qa_runner.php.
     */
    function itm_mbqa_runner_row_tag(string $table, int $companyId, int $sequence): string
    {
        $table = strtolower(trim($table));
        if ($table === '' || !preg_match('/^[a-z0-9_]+$/', $table)) {
            return '';
        }

        $companyId = max(0, $companyId);
        $sequence = max(0, $sequence);
        $suffix = substr(md5($table . (string)$companyId . (string)$sequence), 0, 6);

        return 'MBQA-' . $table . '-' . $companyId . '-' . $sequence . '-' . $suffix;
    }
}

if (!function_exists('itm_username_is_mbqa_runner_seeded')) {
    /**
     * True only for usernames produced by the QA runner's MBQA tag (not arbitrary "mbqa-*" names).
     */
    function itm_username_is_mbqa_runner_seeded(string $username): bool
    {
        $username = strtolower(trim($username));
        if ($username === '') {
            return false;
        }

        return (bool)preg_match('/^mbqa-[a-z0-9_]+-\d+-\d+-[a-f0-9]{6}$/', $username);
    }
}

if (!function_exists('itm_username_is_reserved_qa_import_marker')) {
    /**
     * Reserved prefix for Excel/import tooling; must be longer than a casual "mbqa-jane" style name.
     */
    function itm_username_is_reserved_qa_import_marker(string $username): bool
    {
        $username = strtolower(trim($username));
        if ($username === '' || strlen($username) < 14) {
            return false;
        }

        if (strncmp($username, 'qa-import-', 10) !== 0) {
            return false;
        }

        return (bool)preg_match('/^qa-import-[a-z0-9][a-z0-9._-]*$/', $username);
    }
}

if (!function_exists('itm_user_company_assignment_bypasses_admin_delete_guard')) {
    function itm_user_company_assignment_bypasses_admin_delete_guard(string $username): bool
    {
        return itm_username_is_mbqa_runner_seeded($username)
            || itm_username_is_reserved_qa_import_marker($username);
    }
}
