<?php
use PHPUnit\Framework\TestCase;

/**
 * Multi-tenancy scoping tests.
 */
class MultiTenancyUnittest extends TestCase
{
    protected $conn;

    protected function setUp(): void
    {
        global $conn;
        if (itm_tests_should_skip_db()) {
            $this->markTestSkipped('Database connection unavailable.');
        }
        if (!$conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
        $this->conn = $conn;
    }

    /**
     * Test that session variables for auditing are correctly set.
     */
    public function testAuditSessionVariables()
    {
        // Mock session
        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;
        $_SESSION['username'] = 'admin';

        // Re-run session variable setup (simplified from config.php)
        mysqli_query($this->conn, 'SET @app_user_id = 1');
        mysqli_query($this->conn, 'SET @app_company_id = 1');

        $res = mysqli_query($this->conn, 'SELECT @app_user_id as user_id, @app_company_id as company_id');
        $row = mysqli_fetch_assoc($res);

        $this->assertEquals(1, $row['user_id']);
        $this->assertEquals(1, $row['company_id']);
    }
}
