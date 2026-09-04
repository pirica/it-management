<?php
/**
 * Employees create/edit profile row persistence via prepared statements.
 *
 * Why: create.php and edit.php shared bespoke string INSERT/UPDATE; centralize bind
 * normalization so POST saves use mysqli_prepare + bind_param instead of escape + concat.
 */

declare(strict_types=1);

if (!function_exists('itm_employees_profile_nullable_string')) {
    function itm_employees_profile_nullable_string($value): ?string
    {
        $trimmed = trim((string)$value);

        return $trimmed === '' ? null : $trimmed;
    }
}

if (!function_exists('itm_employees_profile_nullable_int')) {
    function itm_employees_profile_nullable_int($value): ?int
    {
        $trimmed = trim((string)$value);
        if ($trimmed === '') {
            return null;
        }

        return (int)$trimmed;
    }
}

if (!function_exists('itm_employees_profile_nullable_date')) {
    function itm_employees_profile_nullable_date($raw): ?string
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return null;
        }

        require_once __DIR__ . '/itm_date_format.php';

        return itm_parse_date_input($raw);
    }
}

if (!function_exists('itm_employees_profile_normalize_post_row')) {
    /**
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    function itm_employees_profile_normalize_post_row(array $form): array
    {
        $employmentStatusRaw = trim((string)($form['employment_status_id'] ?? ''));
        $employmentStatusId = $employmentStatusRaw === '' ? 1 : (int)$employmentStatusRaw;

        return [
            'first_name' => trim((string)($form['first_name'] ?? '')),
            'last_name' => trim((string)($form['last_name'] ?? '')),
            'display_name' => itm_employees_profile_nullable_string($form['display_name'] ?? ''),
            'full_name' => itm_employees_profile_nullable_string($form['full_name'] ?? ''),
            'work_email' => itm_employees_profile_nullable_string($form['work_email'] ?? ''),
            'personal_email' => itm_employees_profile_nullable_string($form['personal_email'] ?? ''),
            'external_id' => itm_employees_profile_nullable_string($form['external_id'] ?? ''),
            'insurance_n' => itm_employees_profile_nullable_string($form['insurance_n'] ?? ''),
            'username' => itm_employees_profile_nullable_string($form['username'] ?? ''),
            'employee_code' => itm_employees_profile_nullable_string($form['employee_code'] ?? ''),
            'department_id' => itm_employees_profile_nullable_int($form['department_id'] ?? ''),
            'location_id' => itm_employees_profile_nullable_int($form['location_id'] ?? ''),
            'job_code' => itm_employees_profile_nullable_string($form['job_code'] ?? ''),
            'comments' => itm_employees_profile_nullable_string($form['comments'] ?? ''),
            'mobile_phone' => itm_employees_profile_nullable_string($form['mobile_phone'] ?? ''),
            'external_number' => itm_employees_profile_nullable_string($form['external_number'] ?? ''),
            'dect' => itm_employees_profile_nullable_string($form['dect'] ?? ''),
            'extension' => itm_employees_profile_nullable_string($form['extension'] ?? ''),
            'on_contacts' => (int)($form['on_contacts'] ?? 0),
            'on_orgchart' => (int)($form['on_orgchart'] ?? 0),
            'raw_status_code' => itm_employees_profile_nullable_string($form['raw_status_code'] ?? ''),
            'employment_status_id' => $employmentStatusId,
            'employee_position_id' => itm_employees_profile_nullable_int($form['employee_position_id'] ?? ''),
            'reports_to' => itm_employees_profile_nullable_int($form['reports_to'] ?? ''),
            'office_key_card_department_id' => itm_employees_profile_nullable_int($form['office_key_card_department_id'] ?? ''),
            'workstation_mode_id' => itm_employees_profile_nullable_int($form['workstation_mode_id'] ?? ''),
            'assignment_type_id' => itm_employees_profile_nullable_int($form['assignment_type_id'] ?? ''),
            'request_date' => itm_employees_profile_nullable_date($form['request_date'] ?? ''),
            'requested_by' => itm_employees_profile_nullable_string($form['requested_by'] ?? ''),
            'termination_requested_by' => itm_employees_profile_nullable_string($form['termination_requested_by'] ?? ''),
            'start_date' => itm_employees_profile_nullable_date($form['start_date'] ?? ''),
            'employee_type_id' => itm_employees_profile_nullable_int($form['employee_type_id'] ?? ''),
            'termination_date' => itm_employees_profile_nullable_date($form['termination_date'] ?? ''),
            'birthday' => itm_employees_profile_nullable_date($form['birthday'] ?? ''),
            'hide_year' => (int)($form['hide_year'] ?? 0),
            'role_id' => itm_employees_profile_nullable_int($form['role_id'] ?? ''),
            'access_level_id' => itm_employees_profile_nullable_int($form['access_level_id'] ?? ''),
            'active' => (int)($form['active'] ?? 1),
        ];
    }
}

if (!function_exists('itm_employees_profile_fetch_row')) {
    /**
     * @return array<string, mixed>|null
     */
    function itm_employees_profile_fetch_row(mysqli $conn, int $companyId, int $employeeId): ?array
    {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = ($result && mysqli_num_rows($result) === 1) ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_employees_profile_update_photo_prepared')) {
    function itm_employees_profile_update_photo_prepared(mysqli $conn, int $companyId, int $employeeId, string $photoFilename): bool
    {
        $stmt = mysqli_prepare($conn, 'UPDATE employees SET photo = ? WHERE id = ? AND company_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'sii', $photoFilename, $employeeId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_employees_profile_insert_prepared')) {
    /**
     * @param array<string, mixed> $row
     * @return array{ok:bool,id:int,errno:int,error:string}
     */
    function itm_employees_profile_insert_prepared(mysqli $conn, int $companyId, array $row): array
    {
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO employees (
                company_id, first_name, last_name, display_name, full_name, work_email, personal_email, external_id, insurance_n, username, employee_code,
                department_id, location_id, job_code, comments, mobile_phone, external_number, dect, extension, on_contacts, on_orgchart, raw_status_code, employment_status_id,
                employee_position_id, reports_to, office_key_card_department_id, workstation_mode_id, assignment_type_id,
                request_date, requested_by, termination_requested_by,
                start_date, employee_type_id, termination_date, birthday, hide_year, role_id, access_level_id, active
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?
            )'
        );
        if (!$stmt) {
            return [
                'ok' => false,
                'id' => 0,
                'errno' => (int)mysqli_errno($conn),
                'error' => (string)mysqli_error($conn),
            ];
        }

        $firstName = (string)$row['first_name'];
        $lastName = (string)$row['last_name'];
        $displayName = $row['display_name'];
        $fullName = $row['full_name'];
        $workEmail = $row['work_email'];
        $personalEmail = $row['personal_email'];
        $externalId = $row['external_id'];
        $insuranceN = $row['insurance_n'];
        $username = $row['username'];
        $employeeCode = $row['employee_code'];
        $departmentId = $row['department_id'];
        $locationId = $row['location_id'];
        $jobCode = $row['job_code'];
        $comments = $row['comments'];
        $mobilePhone = $row['mobile_phone'];
        $externalNumber = $row['external_number'];
        $dect = $row['dect'];
        $extension = $row['extension'];
        $onContacts = (int)$row['on_contacts'];
        $onOrgchart = (int)$row['on_orgchart'];
        $rawStatusCode = $row['raw_status_code'];
        $employmentStatusId = (int)$row['employment_status_id'];
        $employeePositionId = $row['employee_position_id'];
        $reportsTo = $row['reports_to'];
        $officeDeptId = $row['office_key_card_department_id'];
        $workstationModeId = $row['workstation_mode_id'];
        $assignmentTypeId = $row['assignment_type_id'];
        $requestDate = $row['request_date'];
        $requestedBy = $row['requested_by'];
        $terminationRequestedBy = $row['termination_requested_by'];
        $startDate = $row['start_date'];
        $employeeTypeId = $row['employee_type_id'];
        $terminationDate = $row['termination_date'];
        $birthday = $row['birthday'];
        $hideYear = (int)$row['hide_year'];
        $roleId = $row['role_id'];
        $accessLevelId = $row['access_level_id'];
        $active = (int)$row['active'];

        mysqli_stmt_bind_param(
            $stmt,
            'isssssssssssiissssssiisiiiiisssisssiiii',
            $companyId,
            $firstName,
            $lastName,
            $displayName,
            $fullName,
            $workEmail,
            $personalEmail,
            $externalId,
            $insuranceN,
            $username,
            $employeeCode,
            $departmentId,
            $locationId,
            $jobCode,
            $comments,
            $mobilePhone,
            $externalNumber,
            $dect,
            $extension,
            $onContacts,
            $onOrgchart,
            $rawStatusCode,
            $employmentStatusId,
            $employeePositionId,
            $reportsTo,
            $officeDeptId,
            $workstationModeId,
            $assignmentTypeId,
            $requestDate,
            $requestedBy,
            $terminationRequestedBy,
            $startDate,
            $employeeTypeId,
            $terminationDate,
            $birthday,
            $hideYear,
            $roleId,
            $accessLevelId,
            $active
        );

        $ok = mysqli_stmt_execute($stmt);
        $newId = $ok ? (int)mysqli_insert_id($conn) : 0;
        $errno = (int)mysqli_errno($conn);
        $error = (string)mysqli_error($conn);
        mysqli_stmt_close($stmt);

        return [
            'ok' => (bool)$ok,
            'id' => $newId,
            'errno' => $errno,
            'error' => $error,
        ];
    }
}

if (!function_exists('itm_employees_profile_update_prepared')) {
    /**
     * @param array<string, mixed> $row
     * @return array{ok:bool,errno:int,error:string}
     */
    function itm_employees_profile_update_prepared(
        mysqli $conn,
        int $companyId,
        int $employeeId,
        array $row,
        ?string $photoFilename
    ): array {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE employees SET
                first_name = ?, last_name = ?, display_name = ?, full_name = ?,
                work_email = ?, personal_email = ?, external_id = ?, insurance_n = ?, username = ?, employee_code = ?,
                department_id = ?, location_id = ?, job_code = ?, comments = ?, mobile_phone = ?, external_number = ?, dect = ?, extension = ?,
                on_contacts = ?, on_orgchart = ?, raw_status_code = ?, employment_status_id = ?,
                employee_position_id = ?, reports_to = ?, office_key_card_department_id = ?, workstation_mode_id = ?, assignment_type_id = ?,
                request_date = ?, requested_by = ?, termination_requested_by = ?,
                start_date = ?, employee_type_id = ?, termination_date = ?, birthday = ?, hide_year = ?, photo = ?,
                role_id = ?, access_level_id = ?, active = ?
            WHERE id = ? AND company_id = ? AND is_hidden = 0 LIMIT 1'
        );
        if (!$stmt) {
            return [
                'ok' => false,
                'errno' => (int)mysqli_errno($conn),
                'error' => (string)mysqli_error($conn),
            ];
        }

        $firstName = (string)$row['first_name'];
        $lastName = (string)$row['last_name'];
        $displayName = $row['display_name'];
        $fullName = $row['full_name'];
        $workEmail = $row['work_email'];
        $personalEmail = $row['personal_email'];
        $externalId = $row['external_id'];
        $insuranceN = $row['insurance_n'];
        $username = $row['username'];
        $employeeCode = $row['employee_code'];
        $departmentId = $row['department_id'];
        $locationId = $row['location_id'];
        $jobCode = $row['job_code'];
        $comments = $row['comments'];
        $mobilePhone = $row['mobile_phone'];
        $externalNumber = $row['external_number'];
        $dect = $row['dect'];
        $extension = $row['extension'];
        $onContacts = (int)$row['on_contacts'];
        $onOrgchart = (int)$row['on_orgchart'];
        $rawStatusCode = $row['raw_status_code'];
        $employmentStatusId = (int)$row['employment_status_id'];
        $employeePositionId = $row['employee_position_id'];
        $reportsTo = $row['reports_to'];
        $officeDeptId = $row['office_key_card_department_id'];
        $workstationModeId = $row['workstation_mode_id'];
        $assignmentTypeId = $row['assignment_type_id'];
        $requestDate = $row['request_date'];
        $requestedBy = $row['requested_by'];
        $terminationRequestedBy = $row['termination_requested_by'];
        $startDate = $row['start_date'];
        $employeeTypeId = $row['employee_type_id'];
        $terminationDate = $row['termination_date'];
        $birthday = $row['birthday'];
        $hideYear = (int)$row['hide_year'];
        $photo = $photoFilename;
        $roleId = $row['role_id'];
        $accessLevelId = $row['access_level_id'];
        $active = (int)$row['active'];

        mysqli_stmt_bind_param(
            $stmt,
            'ssssssssssiissssssiisiiiiiissssissisiiiii',
            $firstName,
            $lastName,
            $displayName,
            $fullName,
            $workEmail,
            $personalEmail,
            $externalId,
            $insuranceN,
            $username,
            $employeeCode,
            $departmentId,
            $locationId,
            $jobCode,
            $comments,
            $mobilePhone,
            $externalNumber,
            $dect,
            $extension,
            $onContacts,
            $onOrgchart,
            $rawStatusCode,
            $employmentStatusId,
            $employeePositionId,
            $reportsTo,
            $officeDeptId,
            $workstationModeId,
            $assignmentTypeId,
            $requestDate,
            $requestedBy,
            $terminationRequestedBy,
            $startDate,
            $employeeTypeId,
            $terminationDate,
            $birthday,
            $hideYear,
            $photo,
            $roleId,
            $accessLevelId,
            $active,
            $employeeId,
            $companyId
        );

        $ok = mysqli_stmt_execute($stmt);
        $errno = (int)mysqli_errno($conn);
        $error = (string)mysqli_error($conn);
        mysqli_stmt_close($stmt);

        return [
            'ok' => (bool)$ok,
            'errno' => $errno,
            'error' => $error,
        ];
    }
}
