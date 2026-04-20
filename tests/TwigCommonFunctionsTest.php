<?php

use Auxilium\TwigHandling\Extensions\CommonFunctions;
use PHPUnit\Framework\TestCase;

class TwigCommonFunctionsTest extends TestCase
{
    private CommonFunctions $functions;

    protected function setUp(): void
    {
        $this->functions = new CommonFunctions();
    }

    // <editor-fold defaultstate="collapsed" desc="Get Functions">
    public function testGetFunctionsReturnsArray(): void
    {
        $functions = $this->functions->getFunctions();

        $this->assertIsArray($functions);
        $this->assertNotEmpty($functions);
    }

    public function testGetFunctionsContainsExpectedNames(): void
    {
        $functions = $this->functions->getFunctions();
        $names = array_map(fn($f) => $f->getName(), $functions);

        $this->assertContains('GeneratePseudoRandomBytes', $names);
        $this->assertContains('GeneratePseudoRandomCharacters', $names);
        $this->assertContains('GetSystemConfiguration', $names);
        $this->assertContains('GetUserConfiguration', $names);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Generate Pseudo Random Characters">
    public function testGeneratePseudoRandomCharactersLength(): void
    {
        $result = $this->functions->generatePseudoRandomCharacters(10);
        $this->assertSame(10, strlen($result));
    }

    public function testGeneratePseudoRandomCharactersLengthOne(): void
    {
        $result = $this->functions->generatePseudoRandomCharacters(1);
        $this->assertSame(1, strlen($result));
    }

    public function testGeneratePseudoRandomCharactersLengthZero(): void
    {
        $result = $this->functions->generatePseudoRandomCharacters(0);
        $this->assertSame('', $result);
    }

    public function testGeneratePseudoRandomCharactersOnlyUppercaseAlpha(): void
    {
        $result = $this->functions->generatePseudoRandomCharacters(200);
        $this->assertMatchesRegularExpression('/^[A-Z]*$/', $result);
    }

    public function testGeneratePseudoRandomCharactersVariesBetweenCalls(): void
    {
        // with 50 chars from a 26-char alphabet, collisions are VERY unlikely
        $results = [];
        for ($i = 0; $i < 10; $i++)
        {
            $results[] = $this->functions->generatePseudoRandomCharacters(50);
        }

        $this->assertGreaterThan(1, count(array_unique($results)));
    }

    public function testGeneratePseudoRandomCharactersLargeLength(): void
    {
        $result = $this->functions->generatePseudoRandomCharacters(1000);
        $this->assertSame(1000, strlen($result));
        $this->assertMatchesRegularExpression('/^[A-Z]+$/', $result);
    }
    // </editor-fold>
}

