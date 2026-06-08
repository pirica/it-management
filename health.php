echo "=== Permissions ==="
sudo chmod -R 755 /app/

echo "=== Creating health check endpoint ==="
sudo bash -c "cat >/app/health.php << 'EOF'"
<?php
header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'timestamp' => time(),
    'php' => phpversion(),
    'mysql' => shell_exec('mysqladmin ping 2>/dev/null')
]);
?>
EOF
