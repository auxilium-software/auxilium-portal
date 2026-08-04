<?php

use Auxilium\FormHandling\PayloadProcessor;
use PHPUnit\Framework\TestCase;

class FormBuilderPayloadProcessorTest extends TestCase
{
    private PayloadProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new PayloadProcessor();
    }

    // <editor-fold defaultstate="collapsed" desc="parseFromXML">
    public function testParseFromXmlNull(): void
    {
        $result = $this->processor->parseFromXML(null);
        $this->assertSame([], $result);
    }

    public function testParseFromXmlFalse(): void
    {
        $result = $this->processor->parseFromXML(false);
        $this->assertSame([], $result);
    }

    public function testParseFromXmlJsonString(): void
    {
        $json = '{"name": "Cerys", "role": "developer"}';
        $result = $this->processor->parseFromXML($json);

        $this->assertSame(['name' => 'Cerys', 'role' => 'developer'], $result);
    }

    public function testParseFromXmlInvalidJsonReturnsOriginalString(): void
    {
        $result = $this->processor->parseFromXML('"just a plain string"');
        $this->assertSame('just a plain string', $result);
    }

    public function testParseFromXmlSimpleXmlElement(): void
    {
        $xml = new SimpleXMLElement('<root><name>Cerys</name><role>developer</role></root>');
        $result = $this->processor->parseFromXML($xml);

        $this->assertSame('Cerys', $result['name']);
        $this->assertSame('developer', $result['role']);
    }

    public function testParseFromXmlNestedSimpleXmlElement(): void
    {
        $xml = new SimpleXMLElement('<root><user><name>Cerys</name></user></root>');
        $result = $this->processor->parseFromXML($xml);

        $this->assertIsArray($result['user']);
        $this->assertSame('Cerys', $result['user']['name']);
    }

    public function testParseFromXmlScalarPassthrough(): void
    {
        $this->assertSame(42, $this->processor->parseFromXML(42));
        $this->assertSame(3.14, $this->processor->parseFromXML(3.14));
        $this->assertTrue($this->processor->parseFromXML(true));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="process">
    public function testProcessPlainString(): void
    {
        $result = $this->processor->process('hello', ['formData' => []]);
        $this->assertSame('hello', $result);
    }

    public function testProcessStringWithVariableReference(): void
    {
        $vars = ['formData' => ['name' => 'Cerys']];
        $result = $this->processor->process('$formData["name"]', $vars);

        $this->assertSame('Cerys', $result);
    }

    public function testProcessArrayWithVariableValues(): void
    {
        $vars = ['formData' => ['first' => 'Cerys', 'last' => 'Jones']];
        $payload = [
            'firstName' => '$formData["first"]',
            'lastName' => '$formData["last"]',
            'static' => 'unchanged',
        ];

        $result = $this->processor->process($payload, $vars);

        $this->assertSame('Cerys', $result['firstName']);
        $this->assertSame('Jones', $result['lastName']);
        $this->assertSame('unchanged', $result['static']);
    }

    public function testProcessNestedArray(): void
    {
        $vars = ['formData' => ['email' => 'cerys@example.com']];
        $payload = [
            'user' => [
                'contact' => '$formData["email"]',
            ],
        ];

        $result = $this->processor->process($payload, $vars);

        $this->assertSame('cerys@example.com', $result['user']['contact']);
    }

    public function testProcessArrayWithDynamicKey(): void
    {
        $vars = ['formData' => ['keyName' => 'dynamicKey', 'val' => 'dynamicValue']];
        $payload = [
            '$formData["keyName"]' => '$formData["val"]',
        ];

        $result = $this->processor->process($payload, $vars);

        $this->assertArrayHasKey('dynamicKey', $result);
        $this->assertSame('dynamicValue', $result['dynamicKey']);
    }

    public function testProcessScalarPassthrough(): void
    {
        $vars = ['formData' => []];

        $this->assertSame(42, $this->processor->process(42, $vars));
        $this->assertTrue($this->processor->process(true, $vars));
        $this->assertNull($this->processor->process(null, $vars));
    }

    public function testProcessStringWithoutDollarSignUntouched(): void
    {
        $result = $this->processor->process('no variables here', ['formData' => []]);
        $this->assertSame('no variables here', $result);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="normalizeToArray">
    public function testNormalizeToArrayAlreadyIndexed(): void
    {
        $input = [
            ['endpoint' => '/api/one'],
            ['endpoint' => '/api/two'],
        ];

        $result = $this->processor->normalizeToArray($input);

        $this->assertCount(2, $result);
        $this->assertSame($input, $result);
    }

    public function testNormalizeToArraySingleAssociativeItem(): void
    {
        $input = ['endpoint' => '/api/one', 'method' => 'POST'];

        $result = $this->processor->normalizeToArray($input);

        $this->assertCount(1, $result);
        $this->assertSame('/api/one', $result[0]['endpoint']);
    }

    public function testNormalizeToArrayScalarValue(): void
    {
        $result = $this->processor->normalizeToArray('single');

        $this->assertCount(1, $result);
        $this->assertSame('single', $result[0]);
    }
    // </editor-fold>
}
