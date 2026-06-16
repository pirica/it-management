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
}
