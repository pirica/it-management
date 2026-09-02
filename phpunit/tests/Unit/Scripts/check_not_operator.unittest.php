<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../scripts/lib/itm_not_operator_audit.php';

class CheckNotOperatorUnittest extends ItmScriptCliTestCase
{
    public function testUnaryNotOnVariableIsViolation(): void
    {
        $line = 'if (!$ok) {';
        $hits = itm_not_operator_audit_line($line);
        $this->assertNotEmpty($hits);
        $this->assertSame('unary_not_on_variable', $hits[0]['rule']);
    }

    public function testIsArrayGuardIsClean(): void
    {
        $line = 'if (!is_array($rows)) {';
        $hits = itm_not_operator_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testStrictNotIdenticalIsClean(): void
    {
        $line = 'if ($value !== false) {';
        $hits = itm_not_operator_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testExemptCommentIsClean(): void
    {
        $line = 'if (!$legacy) { // itm-not-operator-exempt: mysqli falsy guard';
        $hits = itm_not_operator_audit_line($line);
        $this->assertSame([], $hits);
    }
}
