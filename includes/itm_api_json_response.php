<?php
/**
 * Shared JSON response helpers for AJAX/API mutation endpoints.
 *
 * Why: Notes, Org Chart, Rack Planner, and switch-port handlers must not return
 * success when tenant-scoped SQL matched zero rows.
 */

if (!function_exists('itm_api_json_response')) {
    function itm_api_json_response(array $payload, $httpStatus = 200)
    {
        $status = (int)$httpStatus;
        if ($status !== 200) {
            http_response_code($status);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('itm_api_mutation_requires_rows')) {
    /**
     * Exit with HTTP 404 when a scoped UPDATE/DELETE matched zero rows.
     *
     * @param int $affectedRows mysqli_stmt_affected_rows() result
     * @param array $successPayload JSON body on success
     * @param array|null $notFoundPayload JSON body on zero rows (defaults ok:false contract)
     */
    function itm_api_mutation_requires_rows($affectedRows, array $successPayload, $notFoundPayload = null)
    {
        if ((int)$affectedRows <= 0) {
            $payload = is_array($notFoundPayload)
                ? $notFoundPayload
                : ['ok' => false, 'error' => 'Record not found or not permitted'];
            itm_api_json_response($payload, 404);
        }

        itm_api_json_response($successPayload, 200);
    }
}
