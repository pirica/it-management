<?php
/**
 * IDF API - Reorder Positions
 * 
 * Batch reassigns slot numbers (position_no) for all devices in an IDF.
 * Used for drag-and-drop reordering in the UI.
 * Logic:
 * - Validation: Ensures all provided position IDs exist and belong to the correct IDF/Company.
 * - Batch Swap: Updates all involved positions to a safe temporary range (+100) 
 *   to avoid duplicate key conflicts during the bulk update.
 * - Final Assignment: Iterates through the requested order and assigns the new 1-10 slot numbers.
 * - Transactional: Ensures the entire rack order is updated as a single atomic operation.
 */

require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

$idf_id = (int)($data['idf_id'] ?? 0);
$order = $data['order'] ?? null;

if ($idf_id <= 0) {
    idf_fail('Invalid idf_id');
}
// Expects an array representing the 10 rack slots.
if (!is_array($order) || count($order) !== 10) {
    idf_fail('Invalid order payload');
}

// Ownership verification.
$stmtIdf = mysqli_prepare($conn, "SELECT id FROM idfs WHERE id=? AND company_id=? LIMIT 1");
if ($stmtIdf) {
    mysqli_stmt_bind_param($stmtIdf, 'ii', $idf_id, $company_id);
    mysqli_stmt_execute($stmtIdf);
    $resIdf = mysqli_stmt_get_result($stmtIdf);
    $foundIdf = $resIdf && mysqli_num_rows($resIdf) === 1;
    mysqli_stmt_close($stmtIdf);

    if (!$foundIdf) {
        idf_fail('IDF not found', 404);
    }
}

// Map the payload to internal IDs and slot numbers.
$map = [];
foreach ($order as $item) {
    if (!is_array($item)) {
        idf_fail('Invalid item');
    }
    $posNo = (int)($item['position_no'] ?? 0);
    $posId = isset($item['position_id']) && $item['position_id'] !== null ? (int)$item['position_id'] : null;
    if ($posNo < 1 || $posNo > 10) {
        idf_fail('Invalid position_no');
    }
    if ($posId !== null && $posId <= 0) {
        idf_fail('Invalid position_id');
    }
    $map[] = ['no' => $posNo, 'id' => $posId];
}

$ids = array_values(array_filter(array_map(static fn($x) => $x['id'], $map), static fn($v) => $v !== null));
$ids = array_values(array_unique($ids));

// Batch verify that all IDs in the request belong to this specific IDF.
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sqlChk = "SELECT COUNT(*) AS c
               FROM idf_positions p
               JOIN idfs i ON i.id=p.idf_id
               WHERE p.id IN ($placeholders) AND p.idf_id=? AND i.company_id=?";
    $stmtChk = mysqli_prepare($conn, $sqlChk);
    if ($stmtChk) {
        $types = str_repeat('i', count($ids)) . 'ii';
        $params = array_merge($ids, [$idf_id, $company_id]);
        mysqli_stmt_bind_param($stmtChk, $types, ...$params);
        mysqli_stmt_execute($stmtChk);
        $resChk = mysqli_stmt_get_result($stmtChk);
        $c = 0;
        if ($resChk && ($r = mysqli_fetch_assoc($resChk))) {
            $c = (int)$r['c'];
        }
        mysqli_stmt_close($stmtChk);

        if ($c !== count($ids)) {
            idf_fail('One or more positions invalid', 400);
        }
    }
}

mysqli_begin_transaction($conn);
try {
    // Stage 1: Move existing devices to a high temporary range (100+) 
    // to prevent "Duplicate entry" errors on the `position_no` field 
    // during the reordering sequence.
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtUpd1 = mysqli_prepare($conn, "UPDATE idf_positions SET position_no=position_no+100 WHERE id IN ($placeholders) AND idf_id=?");
        if ($stmtUpd1) {
            $types = str_repeat('i', count($ids)) . 'i';
            $params = array_merge($ids, [$idf_id]);
            mysqli_stmt_bind_param($stmtUpd1, $types, ...$params);
            mysqli_stmt_execute($stmtUpd1);
            mysqli_stmt_close($stmtUpd1);
        }
    }

    // Stage 2: Assign the final 1-10 slot numbers from the UI mapping.
    $stmtUpd2 = mysqli_prepare($conn, "UPDATE idf_positions SET position_no=? WHERE id=? AND idf_id=? LIMIT 1");
    if ($stmtUpd2) {
        foreach ($map as $item) {
            if ($item['id'] === null) {
                continue;
            }
            $pid = (int)$item['id'];
            $pno = (int)$item['no'];
            mysqli_stmt_bind_param($stmtUpd2, 'iii', $pno, $pid, $idf_id);
            mysqli_stmt_execute($stmtUpd2);
        }
        mysqli_stmt_close($stmtUpd2);
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    idf_fail('Reorder failed: ' . $e->getMessage(), 500);
}

idf_ok();
