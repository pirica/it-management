<?php
/**
 * Why: Email test and alert runner scripts share tenant resolution for CLI and browser.
 */

if (!function_exists('itm_email_script_resolve_company_id')) {
    /**
     * @param array<int,string> $argv
     * @param array<string,mixed> $request
     */
    function itm_email_script_resolve_company_id(array $argv = [], array $request = [], int $defaultCompanyId = 1): int
    {
        $companyId = 0;

        if (PHP_SAPI === 'cli') {
            foreach ($argv as $arg) {
                if (strpos($arg, '--company=') === 0) {
                    $companyId = (int)substr($arg, 10);
                } elseif (strpos($arg, 'company=') === 0) {
                    $companyId = (int)substr($arg, 8);
                }
            }
        } else {
            $companyId = (int)($request['company'] ?? 0);
        }

        if ($companyId <= 0 && isset($_SESSION['company_id'])) {
            $companyId = (int)$_SESSION['company_id'];
        }

        if ($companyId <= 0) {
            $companyId = $defaultCompanyId;
        }

        return $companyId > 0 ? $companyId : 0;
    }
}
