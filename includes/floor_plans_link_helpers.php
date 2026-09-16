<?php
/**
 * Shared helpers for optional floor_plans.it_location_id -> it_locations links.
 * Why: lets IT Locations screens list related floor plans without loading the full gallery module.
 */

function itm_floor_plans_schema_ready(mysqli $conn)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $tables = array('floor_plan_folders', 'floor_plan_tags', 'floor_plans', 'floor_plan_item_tags');
    foreach ($tables as $table) {
        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($table)) {
            $ready = false;
            return false;
        }
        $res = mysqli_query($conn, 'SHOW TABLES LIKE \'' . mysqli_real_escape_string($conn, $table) . '\'');
        if (!$res || mysqli_num_rows($res) === 0) {
            $ready = false;
            return false;
        }
    }
    $ready = true;
    return true;
}

function itm_fetch_floor_plans_for_it_location(mysqli $conn, $companyId, $locationId)
{
    $companyId = (int)$companyId;
    $locationId = (int)$locationId;
    if ($companyId <= 0 || $locationId <= 0 || !itm_floor_plans_schema_ready($conn)) {
        return array();
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT fp.id, fp.display_name, fp.folder_id, fp.mime_type, fp.file_ext, fp.active, f.name AS folder_name
         FROM floor_plans fp
         LEFT JOIN floor_plan_folders f ON f.id = fp.folder_id AND f.company_id = fp.company_id
         WHERE fp.company_id = ? AND fp.it_location_id = ? AND fp.active = 1
         ORDER BY fp.display_name ASC'
    );
    if (!$stmt) {
        return array();
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $locationId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = array();
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function itm_floor_plan_public_url($companyId, $storedFilename)
{
    if (!defined('FLOOR_PLAN_UPLOAD_URL')) {
        return '';
    }
    return FLOOR_PLAN_UPLOAD_URL . rawurlencode((string)(int)$companyId) . '/' . rawurlencode((string)$storedFilename);
}
