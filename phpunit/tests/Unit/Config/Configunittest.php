<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for global configuration and bootstrap helpers.
 */
class ConfigUnittest extends TestCase
{
    /**
     * Test the sanitize() function for XSS protection.
     */
    public function testSanitize()
    {
        $this->assertEquals('Hello &amp; World', sanitize('Hello & World'));
        $this->assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', sanitize('<script>alert(1)</script>'));
        $this->assertEquals('', sanitize(null));
        $this->assertEquals('123', sanitize(123));
    }

    /**
     * Test itm_is_safe_identifier() for SQL identifier validation.
     */
    public function testIsSafeIdentifier()
    {
        $this->assertTrue(itm_is_safe_identifier('valid_table_name'));
        $this->assertTrue(itm_is_safe_identifier('Table123'));
        $this->assertFalse(itm_is_safe_identifier('table-name'));
        $this->assertFalse(itm_is_safe_identifier('table name'));
        $this->assertFalse(itm_is_safe_identifier('table; DROP TABLE users;'));
    }

    /**
     * Test itm_humanize_field_name() for UI label generation.
     */
    public function testHumanizeFieldName()
    {
        $this->assertEquals('Department Name', itm_humanize_field_name('department_id'));
        $this->assertEquals('User Name', itm_humanize_field_name('user_name'));
        $this->assertEquals('ID', itm_humanize_field_name('id'));
        $this->assertEquals('First Name', itm_humanize_field_name('first_name'));
    }

    /**
     * Test itm_field_looks_like_fk_select() for form UI detection.
     */
    public function testFieldLooksLikeFkSelect()
    {
        $this->assertTrue(itm_field_looks_like_fk_select('department_id'));
        $this->assertTrue(itm_field_looks_like_fk_select('created_by'));
        $this->assertTrue(itm_field_looks_like_fk_select('company_id'));
        $this->assertFalse(itm_field_looks_like_fk_select('first_name'));
        $this->assertFalse(itm_field_looks_like_fk_select('active'));
    }

    /**
     * Test itm_resolve_records_per_page() from ui_config helpers.
     */
    public function testResolveRecordsPerPage()
    {
        require_once ROOT_PATH . 'includes/ui_config.php';

        $this->assertSame(25, itm_resolve_records_per_page([]));
        $this->assertSame(50, itm_resolve_records_per_page(['records_per_page' => '50']));
        $this->assertSame(1000000, itm_resolve_records_per_page(['records_per_page' => 'all']));
        $this->assertSame(25, itm_resolve_records_per_page(['records_per_page' => 'invalid']));
        $this->assertSame(25, itm_resolve_records_per_page(['records_per_page' => '0']));
    }

    /**
     * Why: Admin company switch remaps session employee_id on every GET; itm_is_admin()
     * must exist before itm_ensure_company_context_employee_session() in config.php.
     */
    public function testAdminHelperIsDefinedBeforeCompanyContextEnsure()
    {
        $configSource = (string)file_get_contents(ROOT_PATH . 'config/config.php');
        $sessionSource = (string)file_get_contents(ROOT_PATH . 'includes/itm_company_session.php');

        $this->assertNotFalse(
            strpos($sessionSource, 'function itm_is_admin'),
            'itm_is_admin() must live in itm_company_session.php so tenant remap can run during bootstrap.'
        );

        $requirePos = strpos($configSource, 'includes/itm_company_session.php');
        $ensurePos = strpos($configSource, 'itm_ensure_company_context_employee_session($conn');
        $this->assertNotFalse($requirePos);
        $this->assertNotFalse($ensurePos);
        $this->assertLessThan(
            $ensurePos,
            $requirePos,
            'config.php must require itm_company_session.php before tenant employee remap.'
        );
    }
}
