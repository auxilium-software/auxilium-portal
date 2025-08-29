<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

use PHPUnit\Framework\TestCase;

class TwigTest extends TestCase
{
    public function testIntentionalFail()
    {
        $actualResult = Auxilium\MicroTemplate::ui_text(string: "uwu");
        self::assertNotEquals(expected: "uwu2", actual: $actualResult);
    }
}
