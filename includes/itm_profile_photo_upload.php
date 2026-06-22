<?php
/**
 * Shared PNG/JPG profile photo type resolution for module uploads.
 */

if (!function_exists('itm_profile_photo_allowed_extension')) {
    /**
     * Resolve png|jpg from image bytes, finfo MIME, browser type, and filename.
     *
     * @return string|null png|jpg or null when not an allowed profile photo
     */
    function itm_profile_photo_allowed_extension($tmpPath, $originalName, $clientMime = '')
    {
        if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
            $imageInfo = @getimagesize($tmpPath);
            if (is_array($imageInfo) && isset($imageInfo[2])) {
                if ($imageInfo[2] === IMAGETYPE_PNG) {
                    return 'png';
                }
                if ($imageInfo[2] === IMAGETYPE_JPEG) {
                    return 'jpg';
                }
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = strtolower((string)$finfo->file($tmpPath));
            if ($mime === 'image/png') {
                return 'png';
            }
            if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true)) {
                return 'jpg';
            }
        }

        $clientMime = strtolower((string)$clientMime);
        if ($clientMime === 'image/png') {
            return 'png';
        }
        if (in_array($clientMime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true)) {
            return 'jpg';
        }

        // Why: finfo may return application/octet-stream while the browser still sends a .jpg name.
        $lowerName = strtolower((string)$originalName);
        if (preg_match('/\.png$/', $lowerName)) {
            return 'png';
        }
        if (preg_match('/\.jpe?g$/', $lowerName)) {
            return 'jpg';
        }

        return null;
    }
}
