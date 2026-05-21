<?php
/**
 * Audit Logs Module - Edit Placeholder
 *
 * Why: Allowing edits would undermine audit integrity, so this endpoint
 * intentionally routes operators back to the read-only listing.
 */

require '../../config/config.php';

header('Location: index.php');
exit;
