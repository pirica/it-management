<?php
/**
 * Tickets Module - Create/Edit
 *
 * Provides a dynamic form for managing support tickets.
 * Handles:
 * - Ticket profiling (External Code, Title, Description)
 * - Classification (Category, Status, Priority)
 * - Assignments (User, Equipment)
 * - Multiple photo uploads with gallery management
 * - Visual tagging with UI color picker
 */

require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'create', 'tickets');


/**
 * Parses JSON-encoded photo filenames from the database
 */
function ticket_parse_photo_filenames($rawValue): array
{
    if (!is_string($rawValue) || trim($rawValue) === '') { return []; }
    $decoded = json_decode($rawValue, true);
    if (!is_array($decoded)) { return []; }
    return array_values(array_filter(array_map('strval', $decoded), static function ($value) { return $value !== ''; }));
}

/**
 * Returns the public URL for a ticket photo
 */
function ticket_photo_public_path(string $filename): string
{
    return TICKET_UPLOAD_URL . rawurlencode($filename);
}

/**
 * Securely detects the MIME type of an uploaded photo
 */
function ticket_detect_upload_mime_type(string $tmpName): string
{
    if ($tmpName === '' || !is_file($tmpName)) { return ''; }
    if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $tmpName); @finfo_close($finfo);
            if (is_string($mime) && $mime !== '') { return strtolower($mime); }
        }
    }
    // Fallback detection
    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') { return strtolower((string)$imageInfo['mime']); }
    return '';
}

/**
 * Predicts the ID of the ticket being created to use in filenames
 */
function ticket_resolve_upload_ticket_id(mysqli $conn, bool $isEdit, int $id): int
{
    if ($isEdit && $id > 0) { return $id; }
    $statusResult = mysqli_query($conn, "SHOW TABLE STATUS LIKE 'tickets'");
    if ($statusResult) {
        $statusRow = mysqli_fetch_assoc($statusResult);
        if (is_array($statusRow) && isset($statusRow['Auto_increment'])) {
            return (int)$statusRow['Auto_increment'];
        }
    }
    return 0;
}

/**
 * Tenant lookup options with persisted FK row appended when company-scoped query omits it.
 *
 * @return array<int, array{id:int,name:string}>
 */
function tickets_lookup_select_options(mysqli $conn, string $table, int $companyId, $selectedId, string $orderColumn = 'name'): array
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return [];
    }

    $orderColumn = $orderColumn === 'level' ? 'level' : 'name';
    $options = [];
    $sql = 'SELECT id, name FROM `' . str_replace('`', '``', $table) . '` WHERE company_id = '
        . (int)$companyId . ' AND active = 1 ORDER BY `' . $orderColumn . '` ASC';
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $options[] = ['id' => (int)($row['id'] ?? 0), 'name' => (string)($row['name'] ?? '')];
    }

    $selected = (int)$selectedId;
    if ($selected <= 0) {
        return $options;
    }

    foreach ($options as $option) {
        if ($option['id'] === $selected) {
            return $options;
        }
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, name FROM `' . str_replace('`', '``', $table) . '` WHERE id = ? AND company_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return $options;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $selected, $companyId);
    mysqli_stmt_execute($stmt);
    $lookup = mysqli_stmt_get_result($stmt);
    $persisted = ($lookup) ? mysqli_fetch_assoc($lookup) : null;
    mysqli_stmt_close($stmt);
    if (is_array($persisted)) {
        $options[] = ['id' => (int)($persisted['id'] ?? 0), 'name' => (string)($persisted['name'] ?? '')];
    }

    return $options;
}

/**
 * @return array<int, array{id:int,label:string}>
 */
function tickets_equipment_select_options(mysqli $conn, int $companyId, $selectedId): array
{
    if ($companyId <= 0) {
        return [];
    }

    $options = [];
    $res = mysqli_query(
        $conn,
        'SELECT id, name FROM equipment WHERE company_id = ' . (int)$companyId . ' AND active = 1 ORDER BY name ASC'
    );
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $options[] = ['id' => (int)($row['id'] ?? 0), 'label' => (string)($row['name'] ?? '')];
    }

    $selected = (int)$selectedId;
    if ($selected <= 0) {
        return $options;
    }

    foreach ($options as $option) {
        if ($option['id'] === $selected) {
            return $options;
        }
    }

    $stmt = mysqli_prepare($conn, 'SELECT id, name FROM equipment WHERE id = ? AND company_id = ? LIMIT 1');
    if (!$stmt) {
        return $options;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $selected, $companyId);
    mysqli_stmt_execute($stmt);
    $lookup = mysqli_stmt_get_result($stmt);
    $persisted = ($lookup) ? mysqli_fetch_assoc($lookup) : null;
    mysqli_stmt_close($stmt);
    if (is_array($persisted)) {
        $options[] = ['id' => (int)($persisted['id'] ?? 0), 'label' => (string)($persisted['name'] ?? '')];
    }

    return $options;
}

/**
 * Builds equipment quick-add modal fields (type, status, conditional switch RJ45) for Related Equipment select.
 */
function tickets_build_equipment_add_extra_fields_json(mysqli $conn, int $companyId): string
{
    if ($companyId <= 0) {
        return '[]';
    }

    $equipmentTypeOptions = [];
    $switchEquipmentTypeId = 0;
    $typeRes = mysqli_query(
        $conn,
        'SELECT id, name FROM equipment_types WHERE company_id = ' . (int)$companyId . ' ORDER BY name ASC'
    );
    while ($typeRes && ($row = mysqli_fetch_assoc($typeRes))) {
        $typeId = (int)($row['id'] ?? 0);
        $typeName = (string)($row['name'] ?? '');
        $equipmentTypeOptions[] = [
            'value' => $typeId,
            'label' => $typeName,
        ];
        if ($switchEquipmentTypeId === 0 && strcasecmp(trim($typeName), 'switch') === 0) {
            $switchEquipmentTypeId = $typeId;
        }
    }

    $equipmentStatusFieldOptions = [];
    $statusRes = mysqli_query(
        $conn,
        'SELECT id, name FROM equipment_statuses WHERE company_id = ' . (int)$companyId . ' ORDER BY name ASC'
    );
    while ($statusRes && ($row = mysqli_fetch_assoc($statusRes))) {
        $equipmentStatusFieldOptions[] = [
            'value' => (int)($row['id'] ?? 0),
            'label' => (string)($row['name'] ?? ''),
        ];
    }

    $switchRj45FieldOptions = [];
    $rj45Res = mysqli_query(
        $conn,
        'SELECT id, name FROM equipment_rj45 WHERE company_id = ' . (int)$companyId . ' ORDER BY name ASC'
    );
    while ($rj45Res && ($row = mysqli_fetch_assoc($rj45Res))) {
        $switchRj45FieldOptions[] = [
            'value' => (int)($row['id'] ?? 0),
            'label' => (string)($row['name'] ?? ''),
        ];
    }

    $fields = [
        [
            'name' => 'equipment_type_id',
            'label' => 'Equipment Type',
            'type' => 'select',
            'options' => $equipmentTypeOptions,
        ],
        [
            'name' => 'switch_rj45_id',
            'label' => 'RJ45 Ports (required for Switch)',
            'type' => 'select',
            'options' => $switchRj45FieldOptions,
            'required' => false,
            'required_when' => [
                'field' => 'equipment_type_id',
                'equals' => (string)$switchEquipmentTypeId,
            ],
        ],
        [
            'name' => 'status_id',
            'label' => 'Status',
            'type' => 'select',
            'options' => $equipmentStatusFieldOptions,
        ],
    ];

    return json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

/**
 * Builds user quick-add modal fields for Created By / Assigned To selects.
 */
function tickets_build_user_add_extra_fields_json(mysqli $conn, int $companyId): string
{
    if ($companyId <= 0) {
        return '[]';
    }

    $roleOptions = [];
    $roleRes = mysqli_query(
        $conn,
        'SELECT id, name FROM employee_roles WHERE company_id = ' . (int)$companyId . ' ORDER BY name ASC'
    );
    while ($roleRes && ($row = mysqli_fetch_assoc($roleRes))) {
        $roleOptions[] = [
            'value' => (int)($row['id'] ?? 0),
            'label' => (string)($row['name'] ?? ''),
        ];
    }

    $accessLevelOptions = [];
    $accessRes = mysqli_query(
        $conn,
        'SELECT id, name FROM access_levels WHERE company_id = ' . (int)$companyId . ' ORDER BY name ASC'
    );
    while ($accessRes && ($row = mysqli_fetch_assoc($accessRes))) {
        $accessLevelOptions[] = [
            'value' => (int)($row['id'] ?? 0),
            'label' => (string)($row['name'] ?? ''),
        ];
    }

    $fields = [
        ['name' => 'first_name', 'label' => 'First Name', 'type' => 'text', 'required' => true],
        ['name' => 'last_name', 'label' => 'Last Name', 'type' => 'text', 'required' => true],
        ['name' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => false],
        [
            'name' => 'role_id',
            'label' => 'Role',
            'type' => 'select',
            'options' => $roleOptions,
        ],
        [
            'name' => 'access_level_id',
            'label' => 'Access Level',
            'type' => 'select',
            'options' => $accessLevelOptions,
        ],
    ];

    return json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

/**
 * @return array<int, array{id:int,label:string}>
 */
function tickets_user_select_options(mysqli $conn, int $companyId, $selectedId): array
{
    if (!function_exists('itm_user_options_for_company') || !function_exists('itm_user_append_selected_option')) {
        return [];
    }

    $options = itm_user_options_for_company($conn, $companyId);

    return itm_user_append_selected_option($conn, $companyId, $options, $selectedId);
}

function tickets_resolve_created_by_employee_id(mysqli $conn, int $companyId): int
{
    $fromPost = (int)($_POST['created_by_employee_id'] ?? 0);
    if ($fromPost > 0) {
        return $fromPost;
    }

    $sessionUserId = (int)($_SESSION['employee_id'] ?? 0);
    if ($sessionUserId > 0) {
        return $sessionUserId;
    }

    $options = tickets_user_select_options($conn, $companyId, 0);

    return !empty($options) ? (int)$options[0]['id'] : 0;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0 && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
}
$is_edit = $id > 0;
$error = '';
$csrfToken = itm_get_csrf_token();
$ticketUploadPath = TICKET_UPLOAD_PATH;

// Initial state
$data = [
    'ticket_external_code' => '', 'title' => '', 'description' => '',
    'category_id' => '', 'status_id' => '', 'priority_id' => '',
    'created_by_employee_id' => (int)($_SESSION['employee_id'] ?? 0),
    'assigned_to_employee_id' => '', 'equipment_id' => '', 'due_date' => '',
    'tickets_photos' => '', 'active' => 1, 'created_at' => date('Y-m-d\TH:i')
];

// Load existing ticket for editing
if ($is_edit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $q = mysqli_stmt_get_result($stmt);
        if ($q && mysqli_num_rows($q) === 1) {
            $data = mysqli_fetch_assoc($q);
            $data['created_at'] = date('Y-m-d\TH:i', strtotime($data['created_at']));
            $data['due_date'] = $data['due_date'] ? date('Y-m-d', strtotime($data['due_date'])) : '';
        } else { $error = 'Ticket not found.'; $is_edit = false; }
        mysqli_stmt_close($stmt);
    }
}

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    itm_crud_force_active_live($data);

    // Extraction and sanitization
    $ticket_external_code = escape_sql($_POST['ticket_external_code'] ?? '', $conn);
    $title = escape_sql($_POST['title'] ?? '', $conn);
    // Why: Initial description is set on create only; follow-up belongs in Activity comments.
    if ($is_edit && $id > 0) {
        $description = escape_sql((string)($data['description'] ?? ''), $conn);
    } else {
        $description = escape_sql($_POST['description'] ?? '', $conn);
    }

    // Normalization of FK fields
    $category_id = (int)($_POST['category_id'] ?? 0) ?: 'NULL';
    $status_id = (int)($_POST['status_id'] ?? 0) ?: 'NULL';
    $priority_id = (int)($_POST['priority_id'] ?? 0) ?: 'NULL';
    $created_by_employee_id = (int)($_POST['created_by_employee_id'] ?? 0);
    $assigned_to_employee_id = (int)($_POST['assigned_to_employee_id'] ?? 0) ?: 'NULL';
    $equipment_id = (int)($_POST['equipment_id'] ?? 0) ?: 'NULL';
    $due_date = trim((string)($_POST['due_date'] ?? ''));
    $due_date_sql = ($due_date !== '') ? "'" . escape_sql($due_date, $conn) . "'" : 'NULL';

    // --- PHOTO PROCESSING ---
    $ticketPhotoFilenames = ticket_parse_photo_filenames((string)($data['tickets_photos'] ?? ''));
    $ticketPhotoFilenamesToDeleteAfterSave = [];
    $deleteCurrentPhoto = isset($_POST['delete_photo']) && (string)$_POST['delete_photo'] === '1';
    $deletePhotoIndexesRaw = trim((string)($_POST['delete_photo_indexes'] ?? ''));
    $deletePhotoIndexes = [];
    if ($deletePhotoIndexesRaw !== '') {
        $deletePhotoIndexes = array_values(array_unique(array_filter(array_map(static function ($indexValue) {
            if (!is_numeric($indexValue)) {
                return null;
            }
            $index = (int)$indexValue;
            return $index >= 0 ? $index : null;
        }, explode(',', $deletePhotoIndexesRaw)), static function ($value) {
            return $value !== null;
        })));
    }

    // Handle photo removal if requested
    if (!$error && $is_edit && $deleteCurrentPhoto && !empty($ticketPhotoFilenames)) {
        $ticketPhotoFilenamesToDeleteAfterSave = $ticketPhotoFilenames;
        $ticketPhotoFilenames = [];
    } elseif (!$error && $is_edit && !empty($deletePhotoIndexes) && !empty($ticketPhotoFilenames)) {
        foreach ($deletePhotoIndexes as $deletePhotoIndex) {
            if (!array_key_exists($deletePhotoIndex, $ticketPhotoFilenames)) {
                continue;
            }
            $ticketPhotoFilenamesToDeleteAfterSave[] = (string)$ticketPhotoFilenames[$deletePhotoIndex];
            unset($ticketPhotoFilenames[$deletePhotoIndex]);
        }
        $ticketPhotoFilenames = array_values($ticketPhotoFilenames);
        $ticketPhotoFilenamesToDeleteAfterSave = array_values(array_unique($ticketPhotoFilenamesToDeleteAfterSave));
    }

    // Handle new photo uploads
    if (!$error && isset($_FILES['photo']) && is_array($_FILES['photo']['error'])) {
        $uploadTicketId = ticket_resolve_upload_ticket_id($conn, $is_edit, $id);
        foreach ($_FILES['photo']['error'] as $index => $fileError) {
            if ($fileError === UPLOAD_ERR_NO_FILE) continue;
            if ($fileError !== UPLOAD_ERR_OK) { $error = 'Upload failed.'; break; }

            $tmpName = (string)$_FILES['photo']['tmp_name'][$index];
            $name = (string)$_FILES['photo']['name'][$index];

            if (in_array(ticket_detect_upload_mime_type($tmpName), ALLOWED_TYPES, true)) {
                $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION)) ?: 'jpg';
                $photoFilename = 'ticket_' . $uploadTicketId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($tmpName, $ticketUploadPath . $photoFilename)) {
                    $ticketPhotoFilenames[] = $photoFilename;
                }
            }
        }
    }

    // --- DB COMMIT ---
    if (!$is_edit && $created_by_employee_id <= 0) {
        $created_by_employee_id = tickets_resolve_created_by_employee_id($conn, (int)$company_id);
    }

    if (!$title) { $error = 'Ticket title is required.'; }
    elseif ($created_by_employee_id <= 0) { $error = 'Created by user is required.'; }
    else {
        $previousAssigneeId = 0;
        $previousStatusId = 0;
        $previousPriorityId = 0;
        if ($is_edit && $id > 0) {
            $prevStmt = mysqli_prepare($conn, 'SELECT assigned_to_employee_id, status_id, priority_id FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
            if ($prevStmt) {
                mysqli_stmt_bind_param($prevStmt, 'ii', $id, $company_id);
                mysqli_stmt_execute($prevStmt);
                $prevRes = mysqli_stmt_get_result($prevStmt);
                $prevRow = $prevRes ? mysqli_fetch_assoc($prevRes) : null;
                mysqli_stmt_close($prevStmt);
                $previousAssigneeId = (int)($prevRow['assigned_to_employee_id'] ?? 0);
                $previousStatusId = (int)($prevRow['status_id'] ?? 0);
                $previousPriorityId = (int)($prevRow['priority_id'] ?? 0);
            }
        }
        $newAssigneeId = ($assigned_to_employee_id === 'NULL' || $assigned_to_employee_id === null) ? 0 : (int)$assigned_to_employee_id;
        $photos_sql = empty($ticketPhotoFilenames) ? 'NULL' : "'" . escape_sql(json_encode($ticketPhotoFilenames, JSON_UNESCAPED_SLASHES), $conn) . "'";
        $created_at_val = isset($_POST['created_at']) ? "'" . escape_sql(str_replace('T', ' ', $_POST['created_at']) . ':00', $conn) . "'" : 'CURRENT_TIMESTAMP';

        if ($is_edit) {
            $sql = "UPDATE tickets SET
                        ticket_external_code='$ticket_external_code', title='$title', description='$description',
                        category_id=$category_id, status_id=$status_id, priority_id=$priority_id,
                        created_by_employee_id=$created_by_employee_id, assigned_to_employee_id=$assigned_to_employee_id, equipment_id=$equipment_id,
                        due_date=$due_date_sql,
                        tickets_photos=$photos_sql, active=1, created_at=$created_at_val
                    WHERE id=$id AND company_id=$company_id";
        } else {
            $sql = "INSERT INTO tickets
                    (company_id, ticket_external_code, title, description, category_id, status_id, priority_id, created_by_employee_id, assigned_to_employee_id, equipment_id, due_date, tickets_photos, active, created_at)
                    VALUES
                    ($company_id, '$ticket_external_code', '$title', '$description', $category_id, $status_id, $priority_id, $created_by_employee_id, $assigned_to_employee_id, $equipment_id, $due_date_sql, $photos_sql, 1, $created_at_val)";
        }

        if (!$error && itm_run_query($conn, $sql)) {
            // Success: Cleanup physical files for removed photos
            foreach ($ticketPhotoFilenamesToDeleteAfterSave as $df) { @unlink($ticketUploadPath . $df); }
            $savedTicketId = $is_edit ? $id : (int)mysqli_insert_id($conn);
            if ($savedTicketId > 0) {
                require_once '../../includes/itm_search_index.php';
                itm_search_index_after_module_save($conn, 'tickets', (int)$company_id, $savedTicketId);
                if (!$is_edit) {
                    if (function_exists('itm_ticket_sla_apply_on_create')) {
                        $slaPriorityId = ($priority_id === 'NULL' || $priority_id === null) ? 0 : (int)$priority_id;
                        itm_ticket_sla_apply_on_create($conn, $savedTicketId, (int)$company_id, $slaPriorityId);
                    }
                    if (function_exists('itm_webhook_queue_emit_ticket_created')) {
                        require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
                        itm_webhook_queue_emit_ticket_created($conn, (int)$company_id, [
                            'id' => $savedTicketId,
                            'ticket_external_code' => $ticket_external_code,
                            'title' => $title,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
                if (function_exists('itm_automation_rules_dispatch')) {
                    if (!$is_edit) {
                        $ticketContext = itm_automation_rules_build_ticket_context($conn, (int)$company_id, $savedTicketId, [
                            'automation_depth' => 0,
                        ]);
                        itm_automation_rules_dispatch($conn, (int)$company_id, 'ticket.created', $ticketContext);
                    } else {
                        $newStatusId = ($status_id === 'NULL' || $status_id === null) ? 0 : (int)$status_id;
                        if ($newStatusId > 0 && $newStatusId !== $previousStatusId) {
                            $statusContext = itm_automation_rules_build_ticket_context($conn, (int)$company_id, $savedTicketId, [
                                'automation_depth' => 0,
                            ]);
                            $statusContext['previous_status_id'] = $previousStatusId;
                            $statusContext['previous_status_name'] = itm_automation_rules_resolve_ticket_status_name($conn, (int)$company_id, $previousStatusId);
                            itm_automation_rules_dispatch($conn, (int)$company_id, 'ticket.status_changed', $statusContext);
                            if (function_exists('itm_webhook_queue_emit_ticket_status_changed')) {
                                require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
                                itm_webhook_queue_emit_ticket_status_changed($conn, (int)$company_id, [
                                    'id' => $savedTicketId,
                                    'ticket_external_code' => $ticket_external_code,
                                    'title' => $title,
                                    'status_id' => $newStatusId,
                                    'status_name' => (string)($statusContext['status_name'] ?? ''),
                                ], [
                                    'previous_status_id' => $previousStatusId,
                                    'previous_status_name' => (string)($statusContext['previous_status_name'] ?? ''),
                                ]);
                            }
                        }
                        $newPriorityId = ($priority_id === 'NULL' || $priority_id === null) ? 0 : (int)$priority_id;
                        if ($newPriorityId > 0 && $newPriorityId !== $previousPriorityId) {
                            $priorityContext = itm_automation_rules_build_ticket_context($conn, (int)$company_id, $savedTicketId, [
                                'automation_depth' => 0,
                            ]);
                            $priorityContext['previous_priority_id'] = $previousPriorityId;
                            $priorityContext['previous_priority_name'] = itm_automation_rules_resolve_ticket_priority_name($conn, (int)$company_id, $previousPriorityId);
                            itm_automation_rules_dispatch($conn, (int)$company_id, 'ticket.priority_changed', $priorityContext);
                            if (function_exists('itm_webhook_queue_emit_ticket_priority_changed')) {
                                require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
                                itm_webhook_queue_emit_ticket_priority_changed($conn, (int)$company_id, [
                                    'id' => $savedTicketId,
                                    'ticket_external_code' => $ticket_external_code,
                                    'title' => $title,
                                    'priority_id' => $newPriorityId,
                                    'priority_name' => (string)($priorityContext['priority_name'] ?? ''),
                                ], [
                                    'previous_priority_id' => $previousPriorityId,
                                    'previous_priority_name' => (string)($priorityContext['previous_priority_name'] ?? ''),
                                ]);
                            }
                        }
                    }
                }
                $newStatusIdForSurvey = ($status_id === 'NULL' || $status_id === null) ? 0 : (int)$status_id;
                if ($newStatusIdForSurvey > 0 && function_exists('itm_ticket_survey_maybe_issue_on_close')) {
                    if (!$is_edit || $newStatusIdForSurvey !== $previousStatusId) {
                        itm_ticket_survey_maybe_issue_on_close($conn, (int)$company_id, $savedTicketId, $newStatusIdForSurvey);
                    }
                }
                if ($newAssigneeId > 0 && $newAssigneeId !== $previousAssigneeId) {
                    itm_notify_ticket_assigned($conn, (int)$company_id, $newAssigneeId, $savedTicketId, $title, $ticket_external_code);
                }
                if ($is_edit && function_exists('itm_ticket_log_edit_field_changes')) {
                    $newStatusIdForActivity = ($status_id === 'NULL' || $status_id === null) ? 0 : (int)$status_id;
                    $newPriorityIdForActivity = ($priority_id === 'NULL' || $priority_id === null) ? 0 : (int)$priority_id;
                    itm_ticket_log_edit_field_changes(
                        $conn,
                        (int)$company_id,
                        $savedTicketId,
                        (int)$_SESSION['employee_id'],
                        [
                            'status_id' => $previousStatusId,
                            'priority_id' => $previousPriorityId,
                            'assigned_to_employee_id' => $previousAssigneeId,
                        ],
                        [
                            'status_id' => $newStatusIdForActivity,
                            'priority_id' => $newPriorityIdForActivity,
                            'assigned_to_employee_id' => $newAssigneeId,
                        ]
                    );
                }
            }
            header('Location: index.php'); exit;
        }
    }
}

// FETCH REFERENCE DATA FOR DROPDOWNS
$categoryOptions = tickets_lookup_select_options($conn, 'ticket_categories', (int)$company_id, $data['category_id'] ?? 0, 'name');
$statusOptions = tickets_lookup_select_options($conn, 'ticket_statuses', (int)$company_id, $data['status_id'] ?? 0, 'name');
$priorityOptions = tickets_lookup_select_options($conn, 'ticket_priorities', (int)$company_id, $data['priority_id'] ?? 0, 'level');
$createdByOptions = tickets_user_select_options($conn, (int)$company_id, $data['created_by_employee_id'] ?? 0);
$assignedToOptions = tickets_user_select_options($conn, (int)$company_id, $data['assigned_to_employee_id'] ?? 0);
$equipmentOptions = tickets_equipment_select_options($conn, (int)$company_id, $data['equipment_id'] ?? 0);
$ticketEquipmentAddExtraFieldsJson = tickets_build_equipment_add_extra_fields_json($conn, (int)$company_id);
$ticketUserAddExtraFieldsJson = tickets_build_user_add_extra_fields_json($conn, (int)$company_id);
$existingTicketPhotos = ticket_parse_photo_filenames((string)($data['tickets_photos'] ?? ''));
$existingTicketPhotoUrls = [];
foreach ($existingTicketPhotos as $existingTicketPhotoFilename) {
    $existingTicketPhotoUrls[] = ticket_photo_public_path((string)$existingTicketPhotoFilename);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Tickets';
}
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
        $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), basename(dirname($_SERVER['PHP_SELF'])), (string)($crud_title ?? ''));
    ?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
    .photo-preview-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.65);
        z-index: 1200;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .photo-preview-content {
        background: var(--surface, #ffffff);
        border: 1px solid var(--border, #ddd);
        border-radius: 10px;
        max-width: min(90vw, 900px);
        max-height: 90vh;
        overflow: auto;
        padding: 12px;
        text-align: center;
    }
    .photo-preview-content img {
        max-width: 100%;
        max-height: calc(90vh - 120px);
        border-radius: 8px;
    }
    .photo-preview-actions {
        margin-bottom: 10px;
        text-align: right;
    }
    .photo-preview-gallery {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .photo-preview-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .photo-preview-gallery img {
        width: 100%;
        height: auto;
        border: 1px solid var(--border, #ddd);
        border-radius: 8px;
    }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="<?php echo $is_edit ? 'Edit ticket' : 'New ticket'; ?>"><?php echo $is_edit ? '✏️' : '➕'; ?></h1>
            <?php echo itm_render_alert_errors($error ?? ''); ?>

            <div class="card">
                <form id="ticketForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php if ($is_edit && $id > 0): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <?php endif; ?>
                    <?php itm_crud_render_form_hidden_active_input(); ?>

                    <div class="form-row">
                        <div class="form-group"><label>Title *</label><input required name="title" value="<?php echo sanitize($data['title']); ?>"></div>
                        <div class="form-group"><label>External Code</label><input name="ticket_external_code" value="<?php echo sanitize($data['ticket_external_code']); ?>"></div>
                    </div>

                    <div class="form-group"><label>Description</label>
                        <?php if ($is_edit): ?>
                            <textarea name="description" id="ticket-description-field" readonly title="Initial ticket description"><?php echo sanitize($data['description']); ?></textarea>
                            <p class="text-muted" style="margin-top:8px;">Initial description cannot be changed here. Add updates on the <a class="itm-plain-link" href="view.php?id=<?php echo (int)$id; ?>">ticket Activity feed</a>.</p>
                        <?php else: ?>
                            <textarea name="description" id="ticket-description-field"><?php echo sanitize($data['description']); ?></textarea>
                        <?php endif; ?>
                    </div>

                    <?php if (!$is_edit): ?>
                    <div class="card" id="ticket-known-error-suggest" style="margin-bottom:16px;display:none;">
                        <h3 title="Possible known errors">🔍</h3>
                        <ul id="ticket-known-error-suggest-list"></ul>
                    </div>
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" data-addable-select="1" data-add-table="ticket_categories" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1">
                                <option value="">-- Select --</option>
                                <?php foreach ($categoryOptions as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (string)$data['category_id'] === (string)$c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['name']); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status_id" data-addable-select="1" data-add-table="ticket_statuses" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1">
                                <option value="">-- Select --</option>
                                <?php foreach ($statusOptions as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo (string)$data['status_id'] === (string)$s['id'] ? 'selected' : ''; ?>><?php echo sanitize($s['name']); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority_id" data-addable-select="1" data-add-table="ticket_priorities" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1">
                                <option value="">-- Select --</option>
                                <?php foreach ($priorityOptions as $p): ?>
                                    <option value="<?php echo (int)$p['id']; ?>" <?php echo (string)$data['priority_id'] === (string)$p['id'] ? 'selected' : ''; ?>><?php echo sanitize($p['name']); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Created By</label>
                            <select name="created_by_employee_id" required data-addable-select="1" data-add-table="employees" data-add-id-col="id" data-add-label-col="username" data-add-company-scoped="1" data-add-friendly="user" data-add-value-label="Username" data-add-extra-fields="<?php echo sanitize($ticketUserAddExtraFieldsJson); ?>">
                                <option value="">-- Select --</option>
                                <?php foreach ($createdByOptions as $userOption): ?>
                                    <option value="<?php echo (int)$userOption['id']; ?>" <?php echo (string)$data['created_by_employee_id'] === (string)$userOption['id'] ? 'selected' : ''; ?>><?php echo sanitize($userOption['label']); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assigned To</label>
                            <select name="assigned_to_employee_id" data-addable-select="1" data-add-table="employees" data-add-id-col="id" data-add-label-col="username" data-add-company-scoped="1" data-add-friendly="user" data-add-value-label="Username" data-add-extra-fields="<?php echo sanitize($ticketUserAddExtraFieldsJson); ?>">
                                <option value="">-- Select --</option>
                                <?php foreach ($assignedToOptions as $userOption): ?>
                                    <option value="<?php echo (int)$userOption['id']; ?>" <?php echo (string)$data['assigned_to_employee_id'] === (string)$userOption['id'] ? 'selected' : ''; ?>><?php echo sanitize($userOption['label']); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Related Equipment</label>
                            <select name="equipment_id" id="ticketEquipmentSelect" data-addable-select="1" data-add-table="equipment" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="equipment" data-add-extra-fields="<?php echo sanitize($ticketEquipmentAddExtraFieldsJson); ?>">
                                <option value="">-- Select --</option>
                                <?php foreach ($equipmentOptions as $equipmentOption): ?>
                                    <option value="<?php echo (int)$equipmentOption['id']; ?>" <?php echo (string)$data['equipment_id'] === (string)$equipmentOption['id'] ? 'selected' : ''; ?>><?php echo sanitize($equipmentOption['label']); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Created At</label>
                            <?php itm_render_uk_datetime_input('created_at', 'ticket-created-at', (string) ($data['created_at'] ?? '')); ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Due Date</label>
                            <?php itm_render_uk_date_input('due_date', 'ticket-due-date', (string) ($data['due_date'] ?? '')); ?>
                        </div>
                        <div class="form-group"></div>
                    </div>

                    <div class="form-group">
                        <label>Photo Upload</label>
                        <div id="ticketPhotoUploadTarget" class="itm-photo-upload-target" role="button" tabindex="0" aria-label="Upload ticket photos">
                            <p class="itm-dropzone-hint">Drag and drop images here, or click to browse. You can select multiple photos.</p>
                            <input type="file" name="photo[]" id="ticketPhotoInput" accept="image/*" multiple>
                        </div>
                        <div class="form-hint" id="currentPhotoHint">
                            <span id="currentPhotoHintText"><?php echo count($existingTicketPhotos) > 0 ? 'Current photos: ' . count($existingTicketPhotos) : 'Selected photos: 0'; ?></span>
                            <button type="button" class="btn btn-sm" id="openPhotoPreview">🔎</button>
                            <?php if ($is_edit && !empty($existingTicketPhotos)): ?>
                                <input type="hidden" name="delete_photo" id="deletePhotoInput" value="0">
                                <input type="hidden" name="delete_photo_indexes" id="deletePhotoIndexesInput" value="">
                                <button type="button" class="btn btn-sm" id="deletePhotoButton" style="margin-left:8px;">Delete All</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;"><button class="btn btn-primary" type="submit">💾</button><a href="index.php" class="btn">🔙</a></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="photo-preview-modal" id="photoPreviewModal" aria-hidden="true">
    <div class="photo-preview-content" role="dialog" aria-modal="true" aria-label="Ticket photos" onclick="event.stopPropagation()">
        <div class="photo-preview-actions">
            <button type="button" class="btn btn-sm" id="closePhotoPreview">Close</button>
        </div>
        <div class="photo-preview-gallery" id="existingPhotoPreviewGallery">
            <?php foreach ($existingTicketPhotoUrls as $photoIndex => $photoUrl): ?>
                <div class="photo-preview-item">
                    <a class="itm-plain-link" href="<?php echo sanitize($photoUrl); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo sanitize($photoUrl); ?>" alt="Current ticket photo <?php echo (int)$photoIndex + 1; ?>">
                    </a>
                    <?php if ($is_edit): ?>
                        <button type="button" class="btn btn-sm delete-photo-item" data-photo-index="<?php echo (int)$photoIndex; ?>" aria-label="Delete photo <?php echo (int)$photoIndex + 1; ?>">♻️ Delete</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <h4 style="margin:14px 0 8px;">Selected (not saved yet)</h4>
        <div class="photo-preview-gallery" id="pendingPhotoPreviewGallery"></div>
        <p id="photoPreviewEmptyHint" style="margin-top:12px;color:var(--text-muted,#666);display:none;">No photos to preview yet.</p>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/select-add-option.js"></script>
<script src="../../js/itm-upload-helper.js"></script>
<script>
(function () {
    var ticketForm = document.getElementById('ticketForm');
    var uploadTarget = document.getElementById('ticketPhotoUploadTarget');
    var photoInput = document.getElementById('ticketPhotoInput');
    var openPhotoPreview = document.getElementById('openPhotoPreview');
    var photoPreviewModal = document.getElementById('photoPreviewModal');
    var closePhotoPreview = document.getElementById('closePhotoPreview');
    var deletePhotoButton = document.getElementById('deletePhotoButton');
    var deletePhotoInput = document.getElementById('deletePhotoInput');
    var deletePhotoIndexesInput = document.getElementById('deletePhotoIndexesInput');
    var currentPhotoHintText = document.getElementById('currentPhotoHintText');
    var existingPhotoPreviewGallery = document.getElementById('existingPhotoPreviewGallery');
    var pendingPhotoPreviewGallery = document.getElementById('pendingPhotoPreviewGallery');
    var photoPreviewEmptyHint = document.getElementById('photoPreviewEmptyHint');
    var deletePhotoItemButtons = document.querySelectorAll('.delete-photo-item');
    var totalCurrentPhotos = deletePhotoItemButtons.length || (existingPhotoPreviewGallery ? existingPhotoPreviewGallery.querySelectorAll('.photo-preview-item').length : 0);
    var selectedPhotoPreviewUrls = [];
    var pendingDeletedPhotoIndexes = new Set();

    if (typeof itmUploadHelper !== 'undefined') {
        itmUploadHelper.setupById("ticketPhotoUploadTarget", "ticketPhotoInput");
    }

function updateCurrentPhotoHint() {
        if (!currentPhotoHintText) {
            return;
        }
        var selectedPhotoCount = pendingPhotoPreviewGallery ? pendingPhotoPreviewGallery.children.length : 0;
        if (deletePhotoInput && deletePhotoInput.value === '1') {
            currentPhotoHintText.textContent = 'Current photos will be deleted after you save.';
            return;
        }
        if (pendingDeletedPhotoIndexes.size > 0) {
            var remainingPhotos = Math.max(totalCurrentPhotos - pendingDeletedPhotoIndexes.size, 0);
            currentPhotoHintText.textContent = pendingDeletedPhotoIndexes.size + ' photo(s) will be deleted after you save. Remaining: ' + remainingPhotos + '.';
            return;
        }
        if (totalCurrentPhotos > 0) {
            currentPhotoHintText.textContent = 'Current photos: ' + totalCurrentPhotos + '. Selected (not saved): ' + selectedPhotoCount + '.';
            return;
        }
        currentPhotoHintText.textContent = selectedPhotoCount > 0
            ? 'Selected photos: ' + selectedPhotoCount + ' (not saved yet).'
            : 'Selected photos: 0';
    }

    function resetPendingPhotoDeletionState() {
        pendingDeletedPhotoIndexes.clear();
        if (deletePhotoInput) {
            deletePhotoInput.value = '0';
        }
        if (deletePhotoIndexesInput) {
            deletePhotoIndexesInput.value = '';
        }
        if (deletePhotoButton) {
            deletePhotoButton.disabled = false;
        }
    }

    function syncDeletePhotoIndexes() {
        if (!deletePhotoIndexesInput) {
            return;
        }
        deletePhotoIndexesInput.value = Array.from(pendingDeletedPhotoIndexes).sort(function (a, b) { return a - b; }).join(',');
    }

    function updatePhotoPreviewActionState() {
        var visibleExistingPhotos = 0;
        if (existingPhotoPreviewGallery) {
            Array.prototype.forEach.call(existingPhotoPreviewGallery.children, function (item) {
                if (item.style.display !== 'none') {
                    visibleExistingPhotos += 1;
                }
            });
        }
        var selectedPhotoCount = pendingPhotoPreviewGallery ? pendingPhotoPreviewGallery.children.length : 0;
        var hasAnyPhotos = visibleExistingPhotos > 0 || selectedPhotoCount > 0;
        if (openPhotoPreview) {
            openPhotoPreview.disabled = !hasAnyPhotos;
        }
        if (photoPreviewEmptyHint) {
            photoPreviewEmptyHint.style.display = hasAnyPhotos ? 'none' : 'block';
        }
    }

    function renderPendingPhotoPreview() {
        clearPendingPhotoPreview();
        if (!pendingPhotoPreviewGallery || !photoInput || !photoInput.files) {
            updatePhotoPreviewActionState();
            updateCurrentPhotoHint();
            return;
        }

        Array.prototype.forEach.call(photoInput.files, function (file, index) {
            if (!isImageFile(file)) {
                return;
            }
            var previewUrl = URL.createObjectURL(file);
            selectedPhotoPreviewUrls.push(previewUrl);

            var item = document.createElement('div');
            item.className = 'photo-preview-item';
            var image = document.createElement('img');
            image.src = previewUrl;
            image.alt = 'Selected ticket photo ' + (index + 1);
            item.appendChild(image);

            var label = document.createElement('small');
            label.textContent = file.name;
            item.appendChild(label);
            pendingPhotoPreviewGallery.appendChild(item);
        });

        updatePhotoPreviewActionState();
        updateCurrentPhotoHint();
    }

    function hidePhotoModal() {
        if (!photoPreviewModal) {
            return;
        }
        photoPreviewModal.style.display = 'none';
        photoPreviewModal.setAttribute('aria-hidden', 'true');
    }

    resetPendingPhotoDeletionState();
    updateCurrentPhotoHint();
    updatePhotoPreviewActionState();
    window.addEventListener('pageshow', function () {
        resetPendingPhotoDeletionState();
        clearPendingPhotoPreview();
        updateCurrentPhotoHint();
        updatePhotoPreviewActionState();
    });

    if (photoInput) {
        photoInput.addEventListener('change', renderPendingPhotoPreview);
    }

    if (deletePhotoButton && deletePhotoInput) {
        deletePhotoButton.addEventListener('click', function () {
            deletePhotoInput.value = '1';
            pendingDeletedPhotoIndexes.clear();
            syncDeletePhotoIndexes();
            hidePhotoModal();
            updateCurrentPhotoHint();
            if (photoInput) {
                photoInput.value = '';
            }
            deletePhotoButton.disabled = true;
        });
    }

    if (deletePhotoItemButtons.length > 0 && deletePhotoInput) {
        deletePhotoItemButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (deletePhotoInput.value === '1') {
                    return;
                }
                var photoIndex = parseInt(button.getAttribute('data-photo-index') || '', 10);
                if (!Number.isInteger(photoIndex) || photoIndex < 0) {
                    return;
                }
                pendingDeletedPhotoIndexes.add(photoIndex);
                syncDeletePhotoIndexes();
                var photoItem = button.closest('.photo-preview-item');
                if (photoItem) {
                    photoItem.style.display = 'none';
                }
                updateCurrentPhotoHint();
                updatePhotoPreviewActionState();
            });
        });
    }

    if (openPhotoPreview && photoPreviewModal) {
        openPhotoPreview.addEventListener('click', function (event) {
            event.preventDefault();
            photoPreviewModal.style.display = 'flex';
            photoPreviewModal.setAttribute('aria-hidden', 'false');
        });
        photoPreviewModal.addEventListener('click', hidePhotoModal);
        if (closePhotoPreview) {
            closePhotoPreview.addEventListener('click', hidePhotoModal);
        }
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hidePhotoModal();
            }
        });
    }
})();
<?php if (!$is_edit): ?>
(function () {
    var titleInput = document.querySelector('input[name="title"]');
    var descField = document.getElementById('ticket-description-field');
    var panel = document.getElementById('ticket-known-error-suggest');
    var list = document.getElementById('ticket-known-error-suggest-list');
    if (!titleInput || !descField || !panel || !list) {
        return;
    }
    var timer = null;
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    function fetchSuggestions() {
        var title = (titleInput.value || '').trim();
        var description = (descField.value || '').trim();
        if (title.length < 4 && description.length < 4) {
            panel.style.display = 'none';
            list.innerHTML = '';
            return;
        }
        var url = '../problems/api.php?action=suggest&limit=5&title=' + encodeURIComponent(title) + '&description=' + encodeURIComponent(description);
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                list.innerHTML = '';
                if (!data || !data.ok || !data.suggestions || !data.suggestions.length) {
                    panel.style.display = 'none';
                    return;
                }
                panel.style.display = 'block';
                data.suggestions.forEach(function (row) {
                    var li = document.createElement('li');
                    li.style.marginBottom = '10px';
                    var workaround = row.workaround || '';
                    li.innerHTML = '<strong>' + escapeHtml(row.ke_title || row.problem_title || 'Known error') + '</strong>'
                        + ' — ' + escapeHtml(workaround.substring(0, 180))
                        + ' <button type="button" class="btn btn-sm" title="Insert workaround">💾</button>';
                    li.querySelector('button').addEventListener('click', function () {
                        var current = descField.value || '';
                        descField.value = current + (current ? '\n\n' : '') + workaround;
                    });
                    list.appendChild(li);
                });
            })
            .catch(function () {
                panel.style.display = 'none';
            });
    }
    function scheduleFetch() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(fetchSuggestions, 400);
    }
    titleInput.addEventListener('input', scheduleFetch);
    descField.addEventListener('input', scheduleFetch);
})();
<?php endif; ?>
</script>
</body>
</html>
