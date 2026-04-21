<?php

use Auxilium\FormHandling\FormDataHelpers;
use PHPUnit\Framework\TestCase;

class FormBuilderFormDataHelpersTest extends TestCase
{
    // <editor-fold defaultstate="collapsed" desc="collectCheckboxValues">
    public function testCollectCheckboxValuesBasic(): void
    {
        $formData = [
            'colours-red' => 'Red',
            'colours-blue' => 'Blue',
            'colours-green' => 'Green',
        ];

        $result = FormDataHelpers::collectCheckboxValues('colours', $formData);

        $this->assertCount(3, $result);
        $this->assertContains('Red', $result);
        $this->assertContains('Blue', $result);
        $this->assertContains('Green', $result);
    }

    public function testCollectCheckboxValuesSkipsEmptyValues(): void
    {
        $formData = [
            'colours-red' => 'Red',
            'colours-blue' => '',
            'colours-green' => 'Green',
        ];

        $result = FormDataHelpers::collectCheckboxValues('colours', $formData);

        $this->assertCount(2, $result);
        $this->assertContains('Red', $result);
        $this->assertContains('Green', $result);
    }

    public function testCollectCheckboxValuesSkipsBooleanTrue(): void
    {
        $formData = [
            'colours-red' => 'TRUE',
            'colours-blue' => true,
            'colours-green' => 'Green',
        ];

        $result = FormDataHelpers::collectCheckboxValues('colours', $formData);

        $this->assertCount(1, $result);
        $this->assertContains('Green', $result);
    }

    public function testCollectCheckboxValuesWithOtherOption(): void
    {
        $formData = [
            'colours-red' => 'Red',
            'colours-other' => 'on',
            'colours-other-text' => 'Magenta',
        ];

        $result = FormDataHelpers::collectCheckboxValues('colours', $formData);

        $this->assertCount(2, $result);
        $this->assertContains('Red', $result);
        $this->assertContains('Magenta', $result);
    }

    public function testCollectCheckboxValuesWithOtherOptionNoText(): void
    {
        $formData = [
            'colours-red' => 'Red',
            'colours-other' => 'on',
            'colours-other-text' => '',
        ];

        $result = FormDataHelpers::collectCheckboxValues('colours', $formData);

        $this->assertCount(2, $result);
        $this->assertContains('Red', $result);
        $this->assertContains('Other', $result);
    }

    public function testCollectCheckboxValuesNoMatches(): void
    {
        $formData = [
            'other_field' => 'value',
        ];

        $result = FormDataHelpers::collectCheckboxValues('colours', $formData);

        $this->assertEmpty($result);
    }

    public function testCollectCheckboxValuesEmptyFormData(): void
    {
        $result = FormDataHelpers::collectCheckboxValues('colours', []);

        $this->assertEmpty($result);
    }

    public function testCollectCheckboxValuesDoesNotMatchPartialPrefix(): void
    {
        $formData = [
            'coloursExtra-red' => 'Red', // "coloursExtra-" != "colours-"
            'colours-blue' => 'Blue',
        ];

        $result = FormDataHelpers::collectCheckboxValues('colours', $formData);

        $this->assertCount(1, $result);
        $this->assertContains('Blue', $result);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="hasCheckboxValues">
    public function testHasCheckboxValuesTrue(): void
    {
        $formData = ['colours-red' => 'Red'];

        $this->assertTrue(FormDataHelpers::hasCheckboxValues('colours', $formData));
    }

    public function testHasCheckboxValuesFalse(): void
    {
        $this->assertFalse(FormDataHelpers::hasCheckboxValues('colours', []));
    }

    public function testHasCheckboxValuesFalseAllEmpty(): void
    {
        $formData = [
            'colours-red' => '',
            'colours-blue' => '',
        ];

        $this->assertFalse(FormDataHelpers::hasCheckboxValues('colours', $formData));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="countCheckboxValues">
    public function testCountCheckboxValues(): void
    {
        $formData = [
            'colours-red' => 'Red',
            'colours-blue' => 'Blue',
            'colours-green' => 'Green',
        ];

        $this->assertSame(3, FormDataHelpers::countCheckboxValues('colours', $formData));
    }

    public function testCountCheckboxValuesZero(): void
    {
        $this->assertSame(0, FormDataHelpers::countCheckboxValues('colours', []));
    }

    public function testCountCheckboxValuesExcludesEmptyAndBooleanTrue(): void
    {
        $formData = [
            'colours-red' => 'Red',
            'colours-blue' => '',
            'colours-green' => 'TRUE',
        ];

        $this->assertSame(1, FormDataHelpers::countCheckboxValues('colours', $formData));
    }
    // </editor-fold>
}
