<?php

use Auxilium\Utilities\EncodingUtilities;
use PHPUnit\Framework\TestCase;

class EncodingToolsTest extends TestCase
{
    public function testURLEncodingBasic()
    {
        $start = "uwu";
        $encodedExpected = "dXd1";

        $encodedActual = EncodingUtilities::Base64EncodeURLSafe(data: $start);
        self::assertEquals(expected: $encodedExpected, actual: $encodedActual);
        $decodedActual = EncodingUtilities::Base64DecodeURLSafe(data: $encodedActual);
        self::assertEquals(expected: $start, actual: $decodedActual);
    }

    public function testURLEncodingComplex()
    {
        $start = "`~!@#$%^&*()_+-=[]{}|\;:'\",<>/?\n\t";
        $encodedExpected = "YH4hQCMkJV4mKigpXystPVtde318XDs6JyIsPD4vPwoJ";

        $encodedActual = EncodingUtilities::Base64EncodeURLSafe(data: $start);
        self::assertEquals(expected: $encodedExpected, actual: $encodedActual);
        $decodedActual = EncodingUtilities::Base64DecodeURLSafe(data: $encodedActual);
        self::assertEquals(expected: $start, actual: $decodedActual);
    }
}
