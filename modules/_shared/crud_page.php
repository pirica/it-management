<?php
require '../../config/config.php';

if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = $crud_title ?? ucwords(str_replace('_', ' ', $crud_table));
$crud_action = $crud_action ?? 'index';
$pk = 'id';

function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

function cr_table_columns($conn, $table) {
    $cols = [];
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

function cr_fk_map($conn, $table) {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableEsc}'
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $map = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $map[$row['COLUMN_NAME']] = $row;
    }
    return $map;
}

function cr_fk_options($conn, $fk, $company_id) {
    $table = $fk['REFERENCED_TABLE_NAME'];
    $col = $fk['REFERENCED_COLUMN_NAME'];

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];

    $where = '';
    if (in_array('company_id', $available, true) && $company_id > 0) {
        $where = ' WHERE company_id=' . (int)$company_id;
    }

    $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . cr_escape_identifier($labelCol) . " AS label FROM " . cr_escape_identifier($table) . $where . ' ORDER BY label';
    $rows = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    return $rows;
}

function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $available[] = $d['Field'];
    }
    foreach (['name', 'title', 'username', 'code', 'mode_name'] as $candidate) {
        if (in_array($candidate, $available, true)) {
            $labelCol = $candidate;
            break;
        }
    }
    return [
        'label_col' => $labelCol,
        'available' => $available,
    ];
}

function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return !in_array($c['Field'], ['id', 'created_at', 'updated_at'], true);
    }));
}

function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') {
        return '';
    }

    if ($label === 'id') {
        return 'ID';
    }

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);
$hasCompany = false;
foreach ($fieldColumns as $c) {
    if ($c['Field'] === 'company_id') { $hasCompany = true; break; }
}

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';

if ($crud_action === 'delete') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $where = ' WHERE id=' . $id;
        if ($hasCompany && $company_id > 0) {
            $where .= ' AND company_id=' . (int)$company_id;
        }
        mysqli_query($conn, 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1');
    }
    header('Location: ' . $listUrl);
    exit;
}

$errors = [];
$data = [];
foreach ($fieldColumns as $col) {
    $data[$col['Field']] = '';
}

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $where = ' WHERE id=' . $editId;
    if ($hasCompany && $company_id > 0) {
        $where .= ' AND company_id=' . (int)$company_id;
    }
    $q = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1');
    $data = ($q && mysqli_num_rows($q) === 1) ? mysqli_fetch_assoc($q) : [];
    if (!$data) {
        $errors[] = 'Record not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
        if ($isTinyInt) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            continue;
        }

        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];
                $newValueEsc = mysqli_real_escape_string($conn, $newValueRaw);

                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "='" . $newValueEsc . "'";
                if (in_array('company_id', $available, true) && $company_id > 0) {
                    $findSql .= ' AND company_id=' . (int)$company_id;
                }
                $findSql .= ' LIMIT 1';
                $existing = mysqli_query($conn, $findSql);
                if ($existing && mysqli_num_rows($existing) > 0) {
                    $row = mysqli_fetch_assoc($existing);
                    $data[$name] = (string)(int)$row['id'];
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $insertValues = ["'" . $newValueEsc . "'"];
                    if (in_array('company_id', $available, true) && $company_id > 0) {
                        $insertFields[] = '`company_id`';
                        $insertValues[] = (string)(int)$company_id;
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues) . ')';
                    if (mysqli_query($conn, $insertSql)) {
                        $data[$name] = (string)(int)mysqli_insert_id($conn);
                    } else {
                        $errors[] = 'Could not add related value for ' . $name . ': ' . mysqli_error($conn);
                        $data[$name] = 'NULL';
                    }
                }
                continue;
            }
        }

        $value = $_POST[$name] ?? null;
        if ($value === '' || $value === null) {
            $data[$name] = 'NULL';
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $data[$name] = (string)(0 + $value);
        } else {
            $data[$name] = "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $data[$name];
            }
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        } else {
            $sets = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $sets[] = cr_escape_identifier($name) . '=' . $data[$name];
            }
            $where = ' WHERE id=' . $editId;
            if ($hasCompany && $company_id > 0) {
                $where .= ' AND company_id=' . (int)$company_id;
            }
            $sql = 'UPDATE ' . cr_escape_identifier($crud_table) . ' SET ' . implode(',', $sets) . $where . ' LIMIT 1';
        }

        if (mysqli_query($conn, $sql)) {
            header('Location: ' . $listUrl);
            exit;
        }
        $errors[] = 'Database error: ' . mysqli_error($conn);
    }
}

$where = '';
if ($hasCompany && $company_id > 0) {
    $where = ' WHERE company_id=' . (int)$company_id;
}
$rows = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' ORDER BY id DESC LIMIT 200');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?> Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error"><?php echo sanitize(implode(' ', $errors)); ?></div>
            <?php endif; ?>

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h1><?php echo sanitize($crud_title); ?></h1>
                    <a href="create.php" class="btn btn-primary">➕</a>
                </div>
                <div class="card" style="overflow:auto;">
                    <table>
                        <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?php echo sanitize(cr_humanize_field($col['Field'])); ?></th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <?php foreach ($columns as $col): $f = $col['Field']; ?>
                                    <td><?php echo sanitize((string)($row[$f] ?? '')); ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">👁️</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this record?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?php echo count($columns) + 1; ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?><?php echo sanitize($crud_title); ?></h1>
                <form method="POST" class="form-grid" style="max-width:980px;">
                    <?php foreach ($fieldColumns as $col): $name = $col['Field'];
                        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
                        $isDate = str_starts_with($col['Type'], 'date');
                        $isDateTime = str_starts_with($col['Type'], 'datetime');
                        $isText = str_contains($col['Type'], 'text');
                        $val = $data[$name] ?? '';
                        $displayVal = ($val === 'NULL') ? '' : (string)$val;
                    ?>
                        <div class="form-group">
                            <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                            <?php if ($name === 'company_id' && $company_id > 0): ?>
                                <input type="number" name="company_id" value="<?php echo (int)$company_id; ?>" readonly>
                            <?php elseif ($isTinyInt): ?>
                                <label><input type="checkbox" name="<?php echo sanitize($name); ?>" value="1" <?php echo ((int)$displayVal === 1) ? 'checked' : ''; ?>> Enabled</label>
                            <?php elseif (isset($fkMap[$name])): ?>
                                <?php
                                    $opts = cr_fk_options($conn, $fkMap[$name], (int)$company_id);
                                    $fkMeta = cr_fk_metadata($conn, $fkMap[$name]['REFERENCED_TABLE_NAME']);
                                    $isCompanyScoped = in_array('company_id', $fkMeta['available'], true) ? 1 : 0;
                                ?>
                                <select
                                    name="<?php echo sanitize($name); ?>"
                                    data-addable-select="1"
                                    data-add-table="<?php echo sanitize($fkMap[$name]['REFERENCED_TABLE_NAME']); ?>"
                                    data-add-id-col="<?php echo sanitize($fkMap[$name]['REFERENCED_COLUMN_NAME']); ?>"
                                    data-add-label-col="<?php echo sanitize($fkMeta['label_col']); ?>"
                                    data-add-company-scoped="<?php echo $isCompanyScoped; ?>"
                                    data-add-friendly="<?php echo sanitize(strtolower(cr_humanize_field($name))); ?>"
                                >
                                    <option value="">-- Select --</option>
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?>><?php echo sanitize($opt['label']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                            <?php elseif ($isDateTime): ?>
                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                            <?php elseif ($isDate): ?>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
                            <?php elseif ($isText): ?>
                                <textarea name="<?php echo sanitize($name); ?>" rows="4"><?php echo sanitize($displayVal); ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a class="btn" href="index.php">✖️</a>
                    </div>
                </form>

            <?php elseif ($crud_action === 'view'): ?>
                <h1>View <?php echo sanitize($crud_title); ?></h1>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($columns as $col): $f = $col['Field']; ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize(cr_humanize_field($f)); ?></th>
                                <td><?php echo sanitize((string)($data[$f] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;"><a class="btn" href="index.php">Back</a> <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>

</body>
</html>
