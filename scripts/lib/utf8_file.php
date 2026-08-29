<?php
/**
 * UTF-8 text file writes for scripts that emit reports on disk.
 *
 * Why: Windows Notepad and some legacy viewers default to ANSI unless a UTF-8 BOM
 * is present; source files stay UTF-8 without BOM per AGENTS.md.
 */
declare(strict_types=1);

/**
 * Write a UTF-8 text file. Optionally prefix UTF-8 BOM for Windows-friendly viewers.
 */
function itm_write_utf8_text_file(string $path, string $content, bool $withBom = false): bool
{
    if ($withBom && strpos($content, "\xEF\xBB\xBF") !== 0) {
        $content = "\xEF\xBB\xBF" . $content;
    }

    return file_put_contents($path, $content) !== false;
}

/**
 * Read a text file and strip a leading UTF-8 BOM if present (PHP json_decode rejects BOM).
 */
function itm_read_utf8_text_file(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $content = (string)file_get_contents($path);
    if (strpos($content, "\xEF\xBB\xBF") === 0) {
        $content = substr($content, 3);
    }

    return $content;
}
