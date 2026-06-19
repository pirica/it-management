<?php
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for sensitive data leak in authentication attempts.
 *
 * Why: Ensures that user input from login/reset fields is not logged if it matches
 * certain sensitive patterns, or simply that we are aware of this logging behavior.
 */
class AttemptsDataLeakTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }

        require_once __DIR__ . '/../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    public function testAttemptsTableLeaksPlaintextIdentifier()
    {
        $leakedSecret = 'P@ssword_Leaked_' . bin2hex(random_bytes(4));

        // Simulate recording a failed attempt (as done in login.php)
        // We use direct SQL because including login.php might exit or redirect
        $stmt = $this->conn->prepare("INSERT INTO attempts (attempt_source, attempt_type, ip_address, email) VALUES ('login', 'failure', '127.0.0.1', ?)");
        $stmt->bind_param('s', $leakedSecret);
        $stmt->execute();
        $attemptId = $stmt->insert_id;
        $stmt->close();

        // Verify the secret is stored in plaintext
        $res = $this->conn->query("SELECT email FROM attempts WHERE id = $attemptId");
        $row = $res->fetch_assoc();

        $this->assertEquals(
            $leakedSecret,
            $row['email'],
            'VULNERABLE: The attempts table stores the user-provided identifier in plaintext, which can lead to password leaks.'
        );

        // Cleanup
        $this->conn->query("DELETE FROM attempts WHERE id = $attemptId");
    }
}
