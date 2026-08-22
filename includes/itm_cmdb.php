<?php
/**
 * CMDB Lite helpers — configuration items, auto-sync from equipment/IDFs, dependency graph.
 */

if (!function_exists('itm_cmdb_relationship_types')) {
    /**
     * @return array<string,string> slug => human label
     */
    function itm_cmdb_relationship_types(): array
    {
        return [
            'depends_on' => 'Depends on',
            'hosts' => 'Hosts',
            'connects_to' => 'Connects to',
            'runs_on' => 'Runs on',
        ];
    }
}

if (!function_exists('itm_cmdb_relationship_type_label')) {
    function itm_cmdb_relationship_type_label(string $type): string
    {
        $types = itm_cmdb_relationship_types();
        return $types[$type] ?? ucwords(str_replace('_', ' ', $type));
    }
}

if (!function_exists('itm_cmdb_external_ref')) {
    function itm_cmdb_external_ref(string $moduleSlug, int $recordId): string
    {
        $moduleSlug = trim($moduleSlug);
        if ($moduleSlug === '' || $recordId <= 0) {
            return '';
        }
        return $moduleSlug . ':' . $recordId;
    }
}

if (!function_exists('itm_cmdb_builtin_type_seeds')) {
    /**
     * @return array<int,array{name:string,source_slug:string,icon:string}>
     */
    function itm_cmdb_builtin_type_seeds(): array
    {
        return [
            ['name' => 'Server', 'source_slug' => 'builtin:server', 'icon' => '🖥️'],
            ['name' => 'Switch', 'source_slug' => 'builtin:switch', 'icon' => '🔀'],
            ['name' => 'Application', 'source_slug' => 'builtin:application', 'icon' => '📱'],
            ['name' => 'Service', 'source_slug' => 'builtin:service', 'icon' => '⚙️'],
            ['name' => 'IDF', 'source_slug' => 'builtin:idf', 'icon' => '🗄️'],
        ];
    }
}

if (!function_exists('itm_cmdb_seed_types_for_company')) {
    /**
     * Ensure builtin CI types and equipment_type-linked types exist for a tenant.
     */
    function itm_cmdb_seed_types_for_company(mysqli $conn, int $companyId, int $employeeId = 0): void
    {
        if ($companyId <= 0) {
            return;
        }

        foreach (itm_cmdb_builtin_type_seeds() as $seed) {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO configuration_item_types (company_id, name, source_slug, icon, active, created_by)
                 SELECT ?, ?, ?, ?, 1, ?
                 FROM DUAL
                 WHERE NOT EXISTS (
                     SELECT 1 FROM configuration_item_types
                     WHERE company_id = ? AND name = ? AND deleted_at IS NULL
                 )'
            );
            if (!$stmt) {
                continue;
            }
            $name = $seed['name'];
            $sourceSlug = $seed['source_slug'];
            $icon = $seed['icon'];
            mysqli_stmt_bind_param($stmt, 'isssiss', $companyId, $name, $sourceSlug, $icon, $employeeId, $companyId, $name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $eqStmt = mysqli_prepare(
            $conn,
            'SELECT id, name FROM equipment_types WHERE company_id = ? AND deleted_at IS NULL AND active = 1'
        );
        if (!$eqStmt) {
            return;
        }
        mysqli_stmt_bind_param($eqStmt, 'i', $companyId);
        mysqli_stmt_execute($eqStmt);
        $eqRes = mysqli_stmt_get_result($eqStmt);
        while ($eqRes && ($row = mysqli_fetch_assoc($eqRes))) {
            $eqTypeId = (int)($row['id'] ?? 0);
            $eqName = trim((string)($row['name'] ?? ''));
            if ($eqTypeId <= 0 || $eqName === '') {
                continue;
            }
            $sourceSlug = 'equipment_type:' . $eqTypeId;
            $ins = mysqli_prepare(
                $conn,
                'INSERT INTO configuration_item_types (company_id, name, source_slug, icon, active, created_by)
                 SELECT ?, ?, ?, ?, 1, ?
                 FROM DUAL
                 WHERE NOT EXISTS (
                     SELECT 1 FROM configuration_item_types
                     WHERE company_id = ? AND source_slug = ? AND deleted_at IS NULL
                 )'
            );
            if (!$ins) {
                continue;
            }
            $icon = '🖥️';
            mysqli_stmt_bind_param($ins, 'isssiss', $companyId, $eqName, $sourceSlug, $icon, $employeeId, $companyId, $sourceSlug);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
        mysqli_stmt_close($eqStmt);
    }
}

if (!function_exists('itm_cmdb_get_type_id_by_source')) {
    function itm_cmdb_get_type_id_by_source(mysqli $conn, int $companyId, string $sourceSlug): int
    {
        if ($companyId <= 0 || $sourceSlug === '') {
            return 0;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM configuration_item_types
             WHERE company_id = ? AND source_slug = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $sourceSlug);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['id'] ?? 0);
    }
}

if (!function_exists('itm_cmdb_resolve_equipment_type_id')) {
    function itm_cmdb_resolve_equipment_type_id(mysqli $conn, int $companyId, int $equipmentTypeId): int
    {
        itm_cmdb_seed_types_for_company($conn, $companyId);
        if ($equipmentTypeId > 0) {
            $fromEq = itm_cmdb_get_type_id_by_source($conn, $companyId, 'equipment_type:' . $equipmentTypeId);
            if ($fromEq > 0) {
                return $fromEq;
            }
        }
        return itm_cmdb_get_type_id_by_source($conn, $companyId, 'builtin:server');
    }
}

if (!function_exists('itm_cmdb_find_ci_by_record')) {
    function itm_cmdb_find_ci_by_record(mysqli $conn, int $companyId, string $moduleSlug, int $recordId): ?array
    {
        if ($companyId <= 0 || $moduleSlug === '' || $recordId <= 0) {
            return null;
        }
        $externalRef = itm_cmdb_external_ref($moduleSlug, $recordId);
        $stmt = mysqli_prepare(
            $conn,
            'SELECT ci.*, cit.name AS ci_type_name, cit.icon AS ci_type_icon
             FROM configuration_items ci
             INNER JOIN configuration_item_types cit ON cit.id = ci.ci_type_id AND cit.company_id = ci.company_id
             WHERE ci.company_id = ? AND ci.deleted_at IS NULL
               AND (ci.external_ref = ? OR (ci.record_module_slug = ? AND ci.record_id = ?))
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'issi', $companyId, $externalRef, $moduleSlug, $recordId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_cmdb_upsert_ci')) {
    /**
     * @return int CI id or 0 on failure
     */
    function itm_cmdb_upsert_ci(
        mysqli $conn,
        int $companyId,
        int $ciTypeId,
        string $name,
        string $moduleSlug,
        int $recordId,
        int $employeeId = 0
    ): int {
        if ($companyId <= 0 || $ciTypeId <= 0 || trim($name) === '' || $moduleSlug === '' || $recordId <= 0) {
            return 0;
        }

        $existing = itm_cmdb_find_ci_by_record($conn, $companyId, $moduleSlug, $recordId);
        $externalRef = itm_cmdb_external_ref($moduleSlug, $recordId);
        $name = trim($name);

        if ($existing) {
            $ciId = (int)($existing['id'] ?? 0);
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE configuration_items
                 SET ci_type_id = ?, name = ?, external_ref = ?, record_module_slug = ?, record_id = ?,
                     active = 1, updated_by = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
            );
            if (!$stmt) {
                return 0;
            }
            mysqli_stmt_bind_param($stmt, 'isssiiii', $ciTypeId, $name, $externalRef, $moduleSlug, $recordId, $employeeId, $ciId, $companyId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $ciId;
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO configuration_items
             (company_id, ci_type_id, name, external_ref, record_module_slug, record_id, active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iisssii', $companyId, $ciTypeId, $name, $externalRef, $moduleSlug, $recordId, $employeeId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return 0;
        }
        $ciId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return $ciId;
    }
}

if (!function_exists('itm_cmdb_sync_equipment')) {
    function itm_cmdb_sync_equipment(mysqli $conn, int $companyId, int $equipmentId, int $employeeId = 0): int
    {
        if ($companyId <= 0 || $equipmentId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT e.id, e.name, e.equipment_type_id, et.name AS equipment_type_name
             FROM equipment e
             LEFT JOIN equipment_types et ON et.id = e.equipment_type_id AND et.company_id = e.company_id
             WHERE e.id = ? AND e.company_id = ? AND e.deleted_at IS NULL
             LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $equipmentId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return 0;
        }

        $equipmentTypeId = (int)($row['equipment_type_id'] ?? 0);
        $typeName = strtolower(trim((string)($row['equipment_type_name'] ?? '')));
        itm_cmdb_seed_types_for_company($conn, $companyId, $employeeId);

        $ciTypeId = itm_cmdb_resolve_equipment_type_id($conn, $companyId, $equipmentTypeId);
        if ($typeName === 'switch') {
            $switchTypeId = itm_cmdb_get_type_id_by_source($conn, $companyId, 'builtin:switch');
            if ($switchTypeId > 0) {
                $ciTypeId = $switchTypeId;
            }
        }

        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            $name = 'Equipment #' . $equipmentId;
        }

        return itm_cmdb_upsert_ci($conn, $companyId, $ciTypeId, $name, 'equipment', $equipmentId, $employeeId);
    }
}

if (!function_exists('itm_cmdb_sync_idf')) {
    function itm_cmdb_sync_idf(mysqli $conn, int $companyId, int $idfId, int $employeeId = 0): int
    {
        if ($companyId <= 0 || $idfId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name FROM idfs WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $idfId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return 0;
        }

        itm_cmdb_seed_types_for_company($conn, $companyId, $employeeId);
        $ciTypeId = itm_cmdb_get_type_id_by_source($conn, $companyId, 'builtin:idf');
        if ($ciTypeId <= 0) {
            $ciTypeId = itm_cmdb_get_type_id_by_source($conn, $companyId, 'builtin:server');
        }

        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            $name = 'IDF #' . $idfId;
        }

        $ciId = itm_cmdb_upsert_ci($conn, $companyId, $ciTypeId, $name, 'idfs', $idfId, $employeeId);

        // Why: Equipment hosted in this IDF should run_on the IDF CI when both exist.
        if ($ciId > 0) {
            $eqStmt = mysqli_prepare(
                $conn,
                'SELECT id FROM equipment WHERE company_id = ? AND idf_id = ? AND deleted_at IS NULL'
            );
            if ($eqStmt) {
                mysqli_stmt_bind_param($eqStmt, 'ii', $companyId, $idfId);
                mysqli_stmt_execute($eqStmt);
                $eqRes = mysqli_stmt_get_result($eqStmt);
                while ($eqRes && ($eqRow = mysqli_fetch_assoc($eqRes))) {
                    $eqId = (int)($eqRow['id'] ?? 0);
                    if ($eqId <= 0) {
                        continue;
                    }
                    $eqCiId = itm_cmdb_sync_equipment($conn, $companyId, $eqId, $employeeId);
                    if ($eqCiId > 0) {
                        itm_cmdb_add_relationship($conn, $companyId, $ciId, $eqCiId, 'hosts', $employeeId);
                    }
                }
                mysqli_stmt_close($eqStmt);
            }
        }

        return $ciId;
    }
}

if (!function_exists('itm_cmdb_load_adjacency')) {
    /**
     * @return array<int,array<int,string>> child_ci_id => list of parent_ci_id
     */
    function itm_cmdb_load_adjacency(mysqli $conn, int $companyId): array
    {
        $adjacency = [];
        if ($companyId <= 0) {
            return $adjacency;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT parent_ci_id, child_ci_id FROM configuration_item_relationships
             WHERE company_id = ? AND deleted_at IS NULL AND active = 1'
        );
        if (!$stmt) {
            return $adjacency;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $parent = (int)($row['parent_ci_id'] ?? 0);
            $child = (int)($row['child_ci_id'] ?? 0);
            if ($parent <= 0 || $child <= 0) {
                continue;
            }
            if (!isset($adjacency[$child])) {
                $adjacency[$child] = [];
            }
            $adjacency[$child][] = $parent;
        }
        mysqli_stmt_close($stmt);
        return $adjacency;
    }
}

if (!function_exists('itm_cmdb_would_create_cycle')) {
    /**
     * Adding parent -> child (child depends on parent upstream) would cycle if child can reach parent.
     */
    function itm_cmdb_would_create_cycle(mysqli $conn, int $companyId, int $parentCiId, int $childCiId): bool
    {
        if ($parentCiId <= 0 || $childCiId <= 0 || $parentCiId === $childCiId) {
            return true;
        }

        $adjacency = itm_cmdb_load_adjacency($conn, $companyId);
        $queue = [$parentCiId];
        $visited = [];
        while ($queue) {
            $current = array_shift($queue);
            if ($current === $childCiId) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            if (!isset($adjacency[$current])) {
                continue;
            }
            foreach ($adjacency[$current] as $upstreamParent) {
                if (!isset($visited[$upstreamParent])) {
                    $queue[] = $upstreamParent;
                }
            }
        }
        return false;
    }
}

if (!function_exists('itm_cmdb_add_relationship')) {
    function itm_cmdb_add_relationship(
        mysqli $conn,
        int $companyId,
        int $parentCiId,
        int $childCiId,
        string $relationshipType,
        int $employeeId = 0
    ): array {
        $types = itm_cmdb_relationship_types();
        if ($companyId <= 0 || $parentCiId <= 0 || $childCiId <= 0 || !isset($types[$relationshipType])) {
            return ['ok' => false, 'error' => 'Invalid relationship parameters.'];
        }
        if ($parentCiId === $childCiId) {
            return ['ok' => false, 'error' => 'A CI cannot relate to itself.'];
        }
        if (itm_cmdb_would_create_cycle($conn, $companyId, $parentCiId, $childCiId)) {
            return ['ok' => false, 'error' => 'Circular dependency detected. Relationship blocked.'];
        }

        $check = mysqli_prepare(
            $conn,
            'SELECT id FROM configuration_item_relationships
             WHERE company_id = ? AND parent_ci_id = ? AND child_ci_id = ? AND relationship_type = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );
        if ($check) {
            mysqli_stmt_bind_param($check, 'iiis', $companyId, $parentCiId, $childCiId, $relationshipType);
            mysqli_stmt_execute($check);
            $cRes = mysqli_stmt_get_result($check);
            $existing = $cRes ? mysqli_fetch_assoc($cRes) : null;
            mysqli_stmt_close($check);
            if ($existing) {
                return ['ok' => true, 'id' => (int)($existing['id'] ?? 0), 'existing' => true];
            }
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO configuration_item_relationships
             (company_id, parent_ci_id, child_ci_id, relationship_type, active, created_by)
             VALUES (?, ?, ?, ?, 1, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Database error.'];
        }
        mysqli_stmt_bind_param($stmt, 'iiisi', $companyId, $parentCiId, $childCiId, $relationshipType, $employeeId);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_error($conn);
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'error' => $err !== '' ? $err : 'Insert failed.'];
        }
        $id = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return ['ok' => true, 'id' => $id];
    }
}

if (!function_exists('itm_cmdb_delete_relationship')) {
    function itm_cmdb_delete_relationship(mysqli $conn, int $companyId, int $relationshipId, int $employeeId = 0): bool
    {
        if ($companyId <= 0 || $relationshipId <= 0) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE configuration_item_relationships
             SET active = 0, deleted_by = ?, deleted_at = NOW()
             WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $employeeId, $relationshipId, $companyId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affected > 0;
    }
}

if (!function_exists('itm_cmdb_list_relationships_for_ci')) {
    function itm_cmdb_list_relationships_for_ci(mysqli $conn, int $companyId, int $ciId): array
    {
        if ($companyId <= 0 || $ciId <= 0) {
            return ['upstream' => [], 'downstream' => []];
        }

        $upstream = [];
        $downStmt = mysqli_prepare(
            $conn,
            'SELECT r.id, r.relationship_type, r.parent_ci_id, p.name AS related_name, p.id AS related_ci_id,
                    t.name AS related_type_name, t.icon AS related_type_icon
             FROM configuration_item_relationships r
             INNER JOIN configuration_items p ON p.id = r.parent_ci_id AND p.company_id = r.company_id
             INNER JOIN configuration_item_types t ON t.id = p.ci_type_id AND t.company_id = p.company_id
             WHERE r.company_id = ? AND r.child_ci_id = ? AND r.deleted_at IS NULL AND r.active = 1
             ORDER BY p.name'
        );
        if ($downStmt) {
            mysqli_stmt_bind_param($downStmt, 'ii', $companyId, $ciId);
            mysqli_stmt_execute($downStmt);
            $res = mysqli_stmt_get_result($downStmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $upstream[] = $row;
            }
            mysqli_stmt_close($downStmt);
        }

        $downstream = [];
        $upStmt = mysqli_prepare(
            $conn,
            'SELECT r.id, r.relationship_type, r.child_ci_id, c.name AS related_name, c.id AS related_ci_id,
                    t.name AS related_type_name, t.icon AS related_type_icon
             FROM configuration_item_relationships r
             INNER JOIN configuration_items c ON c.id = r.child_ci_id AND c.company_id = r.company_id
             INNER JOIN configuration_item_types t ON t.id = c.ci_type_id AND t.company_id = c.company_id
             WHERE r.company_id = ? AND r.parent_ci_id = ? AND r.deleted_at IS NULL AND r.active = 1
             ORDER BY c.name'
        );
        if ($upStmt) {
            mysqli_stmt_bind_param($upStmt, 'ii', $companyId, $ciId);
            mysqli_stmt_execute($upStmt);
            $res = mysqli_stmt_get_result($upStmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $downstream[] = $row;
            }
            mysqli_stmt_close($upStmt);
        }

        return ['upstream' => $upstream, 'downstream' => $downstream];
    }
}

if (!function_exists('itm_cmdb_build_impact_graph')) {
    /**
     * BFS subgraph for blast-radius / impact view.
     *
     * @return array{nodes:array<int,array>,edges:array<int,array>,root_id:int}
     */
    function itm_cmdb_build_impact_graph(mysqli $conn, int $companyId, int $ciId, int $maxDepth = 8): array
    {
        $result = ['nodes' => [], 'edges' => [], 'root_id' => $ciId];
        if ($companyId <= 0 || $ciId <= 0) {
            return $result;
        }

        $nodeIds = [$ciId => 0];
        $queue = [[$ciId, 0]];
        $visited = [$ciId => true];

        while ($queue) {
            [$currentId, $depth] = array_shift($queue);
            if ($depth >= $maxDepth) {
                continue;
            }

            $rels = itm_cmdb_list_relationships_for_ci($conn, $companyId, $currentId);
            foreach (['upstream', 'downstream'] as $direction) {
                foreach ($rels[$direction] as $rel) {
                    $relatedId = (int)($rel[$direction === 'upstream' ? 'parent_ci_id' : 'child_ci_id'] ?? $rel['related_ci_id'] ?? 0);
                    if ($relatedId <= 0) {
                        continue;
                    }
                    $edgeKey = min($currentId, $relatedId) . '-' . max($currentId, $relatedId) . '-' . ($rel['relationship_type'] ?? '');
                    if (!isset($result['edges'][$edgeKey])) {
                        $parentId = $direction === 'upstream' ? $relatedId : $currentId;
                        $childId = $direction === 'upstream' ? $currentId : $relatedId;
                        $result['edges'][$edgeKey] = [
                            'id' => (int)($rel['id'] ?? 0),
                            'parent_ci_id' => $parentId,
                            'child_ci_id' => $childId,
                            'relationship_type' => (string)($rel['relationship_type'] ?? 'depends_on'),
                        ];
                    }
                    if (!isset($visited[$relatedId])) {
                        $visited[$relatedId] = true;
                        $nodeIds[$relatedId] = $depth + 1;
                        $queue[] = [$relatedId, $depth + 1];
                    }
                }
            }
        }

        if (!$nodeIds) {
            return $result;
        }

        $ids = array_keys($nodeIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids) + 1);
        $sql = "SELECT ci.id, ci.name, ci.external_ref, ci.record_module_slug, ci.record_id,
                       cit.name AS ci_type_name, cit.icon AS ci_type_icon
                FROM configuration_items ci
                INNER JOIN configuration_item_types cit ON cit.id = ci.ci_type_id AND cit.company_id = ci.company_id
                WHERE ci.company_id = ? AND ci.deleted_at IS NULL AND ci.id IN ({$placeholders})";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $params = array_merge([$companyId], $ids);
            $bind = [$types];
            foreach ($params as $i => $val) {
                $bind[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $nid = (int)($row['id'] ?? 0);
                $row['depth'] = $nodeIds[$nid] ?? 0;
                $row['is_root'] = ($nid === $ciId);
                $result['nodes'][$nid] = $row;
            }
            mysqli_stmt_close($stmt);
        }

        $result['edges'] = array_values($result['edges']);
        $result['nodes'] = array_values($result['nodes']);
        return $result;
    }
}

if (!function_exists('itm_cmdb_list_ci_options')) {
    function itm_cmdb_list_ci_options(mysqli $conn, int $companyId, int $excludeCiId = 0): array
    {
        if ($companyId <= 0) {
            return [];
        }
        $sql = 'SELECT ci.id, ci.name, cit.name AS ci_type_name, cit.icon AS ci_type_icon
                FROM configuration_items ci
                INNER JOIN configuration_item_types cit ON cit.id = ci.ci_type_id AND cit.company_id = ci.company_id
                WHERE ci.company_id = ? AND ci.deleted_at IS NULL AND ci.active = 1';
        if ($excludeCiId > 0) {
            $sql .= ' AND ci.id <> ' . (int)$excludeCiId;
        }
        $sql .= ' ORDER BY ci.name';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}
