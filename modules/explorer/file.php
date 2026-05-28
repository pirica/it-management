<?php
$path = $_GET['path'] ?? '';

if (!$path || !file_exists($path)) {
    http_response_code(404);
    exit("File not found.");
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

/* -------------------------
   IMAGENS
------------------------- */
if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
    header("Content-Type: " . mime_content_type($path));
    readfile($path);
    exit;
}

/* -------------------------
   PDF
------------------------- */
if ($ext === 'pdf') {
    header("Content-Type: application/pdf");
    readfile($path);
    exit;
}

/* -------------------------
   TEXTO / CÓDIGO
------------------------- */
header("Content-Type: text/plain; charset=utf-8");
readfile($path);
exit;
