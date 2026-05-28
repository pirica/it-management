<?php
/* ---------------------------------------------------------
   SETUP AUTOMÁTICO DO EXPLORER
--------------------------------------------------------- */

$base = __DIR__ . '/data';
$recycle = __DIR__ . '/recycle_bin';

/* Criar pastas se não existirem */
if (!is_dir($base)) mkdir($base, 0777, true);
if (!is_dir($recycle)) mkdir($recycle, 0777, true);

/* Criar ficheiro de teste */
file_put_contents($base . '/example.txt', "Explorer instalado com sucesso.\n");

/* Mensagem final */
echo "<h2>Setup concluído!</h2>";
echo "<p>As pastas <b>data/</b> e <b>recycle_bin/</b> foram criadas.</p>";
echo "<p>Podes agora abrir o <a href='index.php'>index.php</a>.</p>";
