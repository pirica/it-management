<?php
/**
 * Explorer relative-path normalization and safe ZIP extraction helpers.
 *
 * Why: Segment-boundary ACL in get_full_path() must run on canonical paths so
 * prefixes like ./Private cannot bypass Private/Departments checks.
 */

if (!function_exists('explorer_normalize_relative_path')) {
    /**
     * Collapse . segments, reject .. traversal, and return a canonical relative path.
     *
     * @return string|null Normalized path, empty string for storage root, or null when blocked.
     */
    function explorer_normalize_relative_path($relative_path)
    {
        $relative_path = trim(str_replace('\\', '/', (string)$relative_path), '/');
        if ($relative_path === '') {
            return '';
        }

        $segments = explode('/', $relative_path);
        $normalized = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return null;
            }
            $normalized[] = $segment;
        }

        return implode('/', $normalized);
    }
}

if (!function_exists('explorer_extract_zip_safely')) {
    /**
     * Extract ZIP entries only when the resolved target stays under $destinationDir.
     */
    function explorer_extract_zip_safely(ZipArchive $zip, string $destinationDir): bool
    {
        $destinationDir = rtrim(str_replace('\\', '/', $destinationDir), '/');
        $realDest = realpath($destinationDir);
        if ($realDest === false || !is_dir($realDest)) {
            return false;
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            if (!is_string($entryName) || $entryName === '') {
                return false;
            }

            $entryName = str_replace('\\', '/', $entryName);
            if (strpos($entryName, "\0") !== false) {
                return false;
            }

            $entryName = ltrim($entryName, '/');
            if ($entryName === '' || $entryName === '.') {
                continue;
            }

            if (preg_match('#^[a-zA-Z]:/#', $entryName)) {
                return false;
            }

            $relative = explorer_normalize_relative_path($entryName);
            if ($relative === null) {
                return false;
            }

            $isDirectory = substr($entryName, -1) === '/';
            if ($isDirectory) {
                $relative = rtrim($relative, '/');
            }
            if ($relative === '') {
                continue;
            }

            $targetPath = $realDest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $targetParent = dirname($targetPath);
            if (!is_dir($targetParent) && !mkdir($targetParent, 0755, true) && !is_dir($targetParent)) {
                return false;
            }

            $parentReal = realpath($targetParent);
            if ($parentReal === false || strpos($parentReal, $realDest) !== 0) {
                return false;
            }

            if ($isDirectory) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
                    return false;
                }
                continue;
            }

            $stream = $zip->getStream($zip->getNameIndex($index));
            if ($stream === false) {
                return false;
            }

            $output = fopen($targetPath, 'wb');
            if ($output === false) {
                fclose($stream);
                return false;
            }

            stream_copy_to_stream($stream, $output);
            fclose($stream);
            fclose($output);
        }

        return true;
    }
}
