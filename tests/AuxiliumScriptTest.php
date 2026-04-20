<?php

use Auxilium\Auxilium\AuxiliumScript;
use PHPUnit\Framework\TestCase;

class AuxiliumScriptTest extends TestCase
{
    // <editor-fold defaultstate="collapsed" desc="Empty/null input">
    public function testEmptyStringReturnsNull(): void
    {
        $this->assertNull(AuxiliumScript::evaluate_expression('', []));
    }

    public function testWhitespaceOnlyReturnsNull(): void
    {
        $this->assertNull(AuxiliumScript::evaluate_expression('   ', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="String inputs">
    public function testStringLiteral(): void
    {
        $this->assertSame('hello', AuxiliumScript::evaluate_expression('"hello"', []));
    }

    public function testStringLiteralWithEscapedQuote(): void
    {
        $this->assertSame('say "hi"', AuxiliumScript::evaluate_expression('"say \\"hi\\""', []));
    }

    public function testStringLiteralWithEscapedBackslash(): void
    {
        $this->assertSame('back\\slash', AuxiliumScript::evaluate_expression('"back\\\\slash"', []));
    }

    public function testEmptyStringLiteral(): void
    {
        $this->assertSame('', AuxiliumScript::evaluate_expression('""', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Numeric literals">
    public function testIntegerLiteral(): void
    {
        $result = AuxiliumScript::evaluate_expression('42', []);
        $this->assertSame(42, $result);
    }

    public function testFloatLiteral(): void
    {
        $result = AuxiliumScript::evaluate_expression('3.14', []);
        $this->assertSame(3.14, $result);
    }

    public function testNegativeNumberLiteral(): void
    {
        $result = AuxiliumScript::evaluate_expression('-7', []);
        $this->assertSame(-7, $result);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Boolean keywords (no parentheses)">
    public function testBareTrue(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('true', []));
    }

    public function testBareFalse(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('false', []));
    }

    public function testBareTrueCaseInsensitive(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('TRUE', []));
    }

    public function testBareFalseCaseInsensitive(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('False', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Boolean keywords (with parentheses)">
    public function testTrueFunction(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('true()', []));
    }

    public function testFalseFunction(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('false()', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Variables">
    public function testSimpleVariable(): void
    {
        $this->assertSame('bar', AuxiliumScript::evaluate_expression('$foo', ['foo' => 'bar']));
    }

    public function testUndefinedVariableReturnsNull(): void
    {
        $this->assertNull(AuxiliumScript::evaluate_expression('$nope', ['foo' => 'bar']));
    }

    public function testVariableArrayAccessDoubleQuotes(): void
    {
        $vars = ['formData' => ['name' => 'Cerys']];
        $this->assertSame('Cerys', AuxiliumScript::evaluate_expression('$formData["name"]', $vars));
    }

    public function testVariableArrayAccessSingleQuotes(): void
    {
        $vars = ['formData' => ['name' => 'Cerys']];
        $this->assertSame('Cerys', AuxiliumScript::evaluate_expression("\$formData['name']", $vars));
    }

    public function testVariableArrayAccessMissingKeyReturnsNull(): void
    {
        $vars = ['formData' => ['name' => 'Cerys']];
        $this->assertNull(AuxiliumScript::evaluate_expression('$formData["age"]', $vars));
    }

    public function testVariableArrayAccessOnNonArrayReturnsNull(): void
    {
        $vars = ['formData' => 'not_an_array'];
        $this->assertNull(AuxiliumScript::evaluate_expression('$formData["key"]', $vars));
    }

    public function testVariableArrayAccessUndefinedVarReturnsNull(): void
    {
        $this->assertNull(AuxiliumScript::evaluate_expression('$nope["key"]', []));
    }

    public function testEscapedDollarReturnsLiteral(): void
    {
        $result = AuxiliumScript::evaluate_variable_path('\\$notavar', []);
        $this->assertSame('$notavar', $result);
    }

    public function testNonVariableStringPassthrough(): void
    {
        $result = AuxiliumScript::evaluate_variable_path('plain', []);
        $this->assertSame('plain', $result);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Not">
    public function testNotTrue(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('not(true())', []));
    }

    public function testNotFalse(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('not(false())', []));
    }

    public function testNotNoArgs(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('not()', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="exists()">
    public function testExistsWithValue(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('exists($x)', ['x' => 'hi']));
    }

    public function testExistsWithNull(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('exists($x)', ['x' => null]));
    }

    public function testExistsWithEmptyString(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('exists($x)', ['x' => '']));
    }

    public function testExistsWithFalse(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('exists($x)', ['x' => false]));
    }

    public function testExistsUndefinedVariable(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('exists($nope)', []));
    }

    public function testExistsNoArgs(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('exists()', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="or()">
    public function testOrAllFalse(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('or(false(), false())', []));
    }

    public function testOrOneTrue(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('or(false(), true())', []));
    }

    public function testOrAllTrue(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('or(true(), true())', []));
    }

    public function testOrNoArgs(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('or()', []));
    }

    public function testOrSingleTrue(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('or(true())', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="and()">
    public function testAndAllTrue(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('and(true(), true())', []));
    }

    public function testAndOneFalse(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('and(true(), false())', []));
    }

    public function testAndAllFalse(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('and(false(), false())', []));
    }

    public function testAndNoArgs(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('and()', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="eq() / equals()">
    public function testEqMatchingStrings(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('eq("a", "a")', []));
    }

    public function testEqNonMatchingStrings(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('eq("a", "b")', []));
    }

    public function testEqThreeArgsAllSame(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('eq("x", "x", "x")', []));
    }

    public function testEqThreeArgsOneDifferent(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('eq("x", "x", "y")', []));
    }

    public function testEqWithVariables(): void
    {
        $vars = ['a' => 'same', 'b' => 'same'];
        $this->assertTrue(AuxiliumScript::evaluate_expression('eq($a, $b)', $vars));
    }

    public function testEqTooFewArgs(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('eq("lonely")', []));
    }

    public function testEqualsAlias(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('equals("a", "a")', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="concat()">
    public function testConcatTwoStrings(): void
    {
        $this->assertSame('helloworld', AuxiliumScript::evaluate_expression('concat("hello", "world")', []));
    }

    public function testConcatWithVariable(): void
    {
        $vars = ['name' => 'Cerys'];
        $this->assertSame('Hi Cerys', AuxiliumScript::evaluate_expression('concat("Hi ", $name)', $vars));
    }

    public function testConcatNoArgs(): void
    {
        $this->assertSame('', AuxiliumScript::evaluate_expression('concat()', []));
    }

    public function testConcatSingleArg(): void
    {
        $this->assertSame('only', AuxiliumScript::evaluate_expression('concat("only")', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Nested expressions">
    public function testNestedNotAnd(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('not(and(true(), false()))', []));
    }

    public function testNestedOrInsideAnd(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('and(or(false(), true()), true())', []));
    }

    public function testEqWithConcat(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('eq(concat("a", "b"), "ab")', []));
    }

    public function testDeeplyNested(): void
    {
        $this->assertFalse(AuxiliumScript::evaluate_expression('not(or(and(true(), true()), false()))', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Non-existent function">
    public function testNonExistentFunctionReturnsNull(): void
    {
        $this->assertNull(AuxiliumScript::evaluate_expression('bogus("arg")', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Empty parameters on function with no name">
    public function testEmptyFunctionNameReturnsNull(): void
    {
        $this->assertNull(AuxiliumScript::evaluate_expression('("what")', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Case insensitivity of functions">
    public function testFunctionNameCaseInsensitive(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('NOT(false())', []));
    }

    public function testEqCaseInsensitive(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('EQ("a", "a")', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Bare identifier (not a keyword, not a number, no parentheses)">
    public function testBareIdentifierReturnsString(): void
    {
        $this->assertSame('something', AuxiliumScript::evaluate_expression('something', []));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Whitespace handling">
    public function testLeadingTrailingWhitespaceTrimmed(): void
    {
        $this->assertSame('hello', AuxiliumScript::evaluate_expression('  "hello"  ', []));
    }

    public function testArgsWithExtraWhitespace(): void
    {
        $this->assertTrue(AuxiliumScript::evaluate_expression('eq(  "a"  ,  "a"  )', []));
    }
    // </editor-fold>
}
