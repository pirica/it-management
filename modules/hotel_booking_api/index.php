<?php
/**
 * Why: Partners call api.php directly; index only redirects browsers away from a bare folder URL.
 */
header('Location: api.php');
exit;
