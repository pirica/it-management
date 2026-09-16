<?php
use PHPUnit\Framework\TestCase;

/**
 * Basic CRUD logic tests.
 */
class CRUDUnittest extends TestCase
{
    /**
     * Test SQL splitting for CSV-style values.
     */
    public function testSplitSqlCsv()
    {
        $input = "1, 'Hello', 'World, with comma'";
        $expected = ["1", "'Hello'", "'World, with comma'"];
        $this->assertEquals($expected, itm_split_sql_csv($input));
    }

    /**
     * Test splitting SQL VALUES tuples.
     */
    public function testSplitSqlValueTuples()
    {
        $input = "(1, 'Row 1'), (2, 'Row 2')";
        $expected = ["1, 'Row 1'", "2, 'Row 2'"];
        $this->assertEquals($expected, itm_split_sql_value_tuples($input));
    }

    public function testSplitSqlCsvHandlesQuotedCommasAndEscapes()
    {
        $input = "1, 'Hello', 'World, with comma', 'O''Reilly'";
        $expected = ["1", "'Hello'", "'World, with comma'", "'O''Reilly'"];
        $this->assertSame($expected, itm_split_sql_csv($input));
        $this->assertSame([], itm_split_sql_csv(''));
    }

    public function testSplitSqlValueTuplesHandlesNestedParentheses()
    {
        $input = "(1, 'a'), (2, 'b, c')";
        $this->assertSame(["1, 'a'", "2, 'b, c'"], itm_split_sql_value_tuples($input));
    }
}
