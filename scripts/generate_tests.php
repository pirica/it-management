<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

$metadata = json_decode(file_get_contents(__DIR__ . '/modules_metadata.json'), true);

foreach ($metadata as $moduleName => $info) {
    if (!$info['is_standard']) {
        continue;
    }

    $table = $info['crud_table'];
    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $moduleName))) . 'Test';
    $namespace = 'Tests\Unit\Modules\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $moduleName)));
    $testDir = __DIR__ . '/../phpunit/tests/Unit/Modules/' . str_replace(' ', '', ucwords(str_replace('_', ' ', $moduleName)));

    if (!is_dir($testDir)) {
        mkdir($testDir, 0777, true);
    }

    $columns = [];
    $hasCompanyId = false;
    $res = mysqli_query($conn, "DESCRIBE `$table`");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if ($row['Field'] === 'id' || $row['Field'] === 'created_at' || $row['Field'] === 'updated_at') {
            continue;
        }
        if ($row['Field'] === 'company_id') {
            $hasCompanyId = true;
        }
        $columns[] = $row;
    }

    // Identify Foreign Keys
    $fkMap = [];
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableEsc}'
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $resFk = mysqli_query($conn, $sql);
    while ($resFk && ($rowFk = mysqli_fetch_assoc($resFk))) {
        $fkMap[$rowFk['COLUMN_NAME']] = $rowFk;
    }

    $testContent = "<?php

namespace $namespace;

use PHPUnit\Framework\TestCase;

class $className extends TestCase
{
    private \$conn;
    private \$companyId = 1;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        \$this->conn = \$GLOBALS['conn'];
        if (!\$this->conn) {
            \$this->markTestSkipped('Database connection unavailable.');
        }
    }

    public function testCRUD()
    {
        // 1. Create
        \$data = [];
";

    if ($hasCompanyId) {
        $testContent .= "        \$data['company_id'] = \$this->companyId;\n";
    }

    $insertCols = [];
    $placeholders = [];
    $types = [];
    $setupCode = "";

    if ($hasCompanyId) {
        $insertCols[] = 'company_id';
        $placeholders[] = '?';
        $types[] = 'i';
    }

    foreach ($columns as $col) {
        $fieldName = $col['Field'];
        if ($fieldName === 'company_id') continue;

        $type = $col['Type'];
        $null = $col['Null'];
        
        $val = "null";
        $bindType = "s";

        if (isset($fkMap[$fieldName])) {
            $refTable = $fkMap[$fieldName]['REFERENCED_TABLE_NAME'];
            $refCol = $fkMap[$fieldName]['REFERENCED_COLUMN_NAME'];
            
            // Generate setup code to find a valid ID for the FK
            $setupCode .= "        // Find or fallback for $fieldName ($refTable)\n";
            $setupCode .= "        \$res" . $fieldName . " = mysqli_query(\$this->conn, \"SELECT " . $refCol . " FROM `" . $refTable . "` WHERE \" . (strpos('" . $refTable . "', 'companies') === false && strpos('" . $refTable . "', 'employees') === false ? \"company_id = {\$this->companyId}\" : \"1=1\") . \" LIMIT 1\");\n";
            $setupCode .= "        if (\$row" . $fieldName . " = mysqli_fetch_assoc(\$res" . $fieldName . ")) {\n";
            $setupCode .= "            \$data['" . $fieldName . "'] = \$row" . $fieldName . "['" . $refCol . "'];\n";
            $setupCode .= "        } else {\n";
            $setupCode .= "            // If no existing record, we might need to seed it, but for now we skip this test if mandatory\n";
            if ($null === 'NO') {
                $setupCode .= "            \$this->markTestSkipped('Required dependency " . $refTable . " not found in database.');\n";
            } else {
                $setupCode .= "            \$data['" . $fieldName . "'] = null;\n";
            }
            $setupCode .= "        }\n";
            
            $bindType = "i";
        } else {
            if (strpos($type, 'int') !== false) {
                $val = "1";
                $bindType = "i";
            } elseif (strpos($type, 'varchar') !== false || strpos($type, 'text') !== false) {
                $val = "'Test " . $fieldName . "'";
                $bindType = "s";
            } elseif (strpos($type, 'date') !== false) {
                $val = "date('Y-m-d')";
                $bindType = "s";
            } elseif (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false) {
                $val = "10.50";
                $bindType = "d";
            } elseif (strpos($type, 'enum') !== false) {
                if (preg_match("/enum\((.+)\)/", $type, $enumMatch)) {
                    $opts = explode(",", $enumMatch[1]);
                    $val = trim($opts[0], "'");
                    $val = "'$val'";
                } else {
                    $val = "'default'";
                }
                $bindType = "s";
            }

            if ($null === 'NO' || $fieldName === 'active') {
                $testContent .= "        \$data['$fieldName'] = $val;\n";
            }
        }

        if ($null === 'NO' || $fieldName === 'active') {
            $insertCols[] = "`$fieldName`";
            $placeholders[] = "?";
            $types[] = $bindType;
        }
    }

    $testContent .= $setupCode;

    $typesStr = implode('', $types);
    $colsStr = implode(', ', $insertCols);
    $placeholdersStr = implode(', ', $placeholders);
    
    $testContent .= "
        \$sql = \"INSERT INTO `$table` ($colsStr) VALUES ($placeholdersStr)\";
        \$stmt = mysqli_prepare(\$this->conn, \$sql);
        \$this->assertNotFalse(\$stmt, mysqli_error(\$this->conn));
        
        \$bindValues = [];
";
    foreach ($insertCols as $idx => $colEsc) {
        $c = trim($colEsc, '`');
        $testContent .= "        \$bindValues[] = \$data['$c'];\n";
    }

    $testContent .= "        \$bindTypes = '$typesStr';
        mysqli_stmt_bind_param(\$stmt, \$bindTypes, ...\$bindValues);
        
        \$this->assertTrue(mysqli_stmt_execute(\$stmt));
        \$id = mysqli_insert_id(\$this->conn);
        mysqli_stmt_close(\$stmt);

        // 2. Read
        \$res = mysqli_query(\$this->conn, \"SELECT * FROM `$table` WHERE id = \$id\");
        \$row = mysqli_fetch_assoc(\$res);
        \$this->assertNotNull(\$row);
";
    if ($hasCompanyId) {
        $testContent .= "        \$this->assertEquals(\$this->companyId, \$row['company_id']);\n";
    }

    $testContent .= "
        // 3. Update
";
    
    $updateCol = null;
    foreach ($columns as $col) {
        if ($col['Field'] !== 'company_id' && !isset($fkMap[$col['Field']]) && (strpos($col['Type'], 'varchar') !== false || strpos($col['Type'], 'text') !== false)) {
            $updateCol = $col['Field'];
            break;
        }
    }
    
    if ($updateCol) {
        $testContent .= "        \$updatedValue = 'Updated Value';
        \$updateSql = \"UPDATE `$table` SET `$updateCol` = ? WHERE id = ?\";
        \$stmt = mysqli_prepare(\$this->conn, \$updateSql);
        mysqli_stmt_bind_param(\$stmt, 'si', \$updatedValue, \$id);
        \$this->assertTrue(mysqli_stmt_execute(\$stmt));
        mysqli_stmt_close(\$stmt);

        \$res = mysqli_query(\$this->conn, \"SELECT `$updateCol` FROM `$table` WHERE id = \$id\");
        \$row = mysqli_fetch_assoc(\$res);
        \$this->assertEquals(\$updatedValue, \$row['$updateCol']);
";
    } else {
        $testContent .= "        // No suitable varchar/text column found for update test, skipping update assertion\n";
    }

    $testContent .= "
        // 4. Delete
        \$deleteSql = \"DELETE FROM `$table` WHERE id = ?\";
        \$stmt = mysqli_prepare(\$this->conn, \$deleteSql);
        mysqli_stmt_bind_param(\$stmt, 'i', \$id);
        \$this->assertTrue(mysqli_stmt_execute(\$stmt));
        mysqli_stmt_close(\$stmt);

        \$res = mysqli_query(\$this->conn, \"SELECT COUNT(*) as count FROM `$table` WHERE id = \$id\");
        \$row = mysqli_fetch_assoc(\$res);
        \$this->assertEquals(0, (int)\$row['count']);
    }
}
";

    file_put_contents($testDir . '/' . $className . '.php', $testContent);
}
