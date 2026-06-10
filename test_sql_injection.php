<?php
/**
 * SQL Injection Testing Entry Point
 * 
 * This file acts as a root-level wrapper for the SQL injection testing script
 * located in the scripts directory. It's used to manually verify how the 
 * application handles potentially malicious payloads.
 * 
 * Example usage:
 * http://localhost/test_sql_injection.php?payload=%27%20OR%20%271%27=%271
 */

require __DIR__ . '/scripts/test_sql_injection.php';
