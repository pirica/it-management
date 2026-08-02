<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Base TestCase for CLI audit script subprocess tests.
 *
 * Why: Centralises ItmScriptCliTestTrait loading so individual *unittest.php files
 * avoid file-scope require_once (phpunit/tests/AGENT_NOTES.md load-time side effects).
 */
abstract class ItmScriptCliTestCase extends TestCase
{
    use ItmScriptCliTestTrait;
}
