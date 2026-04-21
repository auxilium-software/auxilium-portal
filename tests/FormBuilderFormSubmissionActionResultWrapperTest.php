<?php

use Auxilium\FormHandling\FormSubmissionActionResultWrapper;
use PHPUnit\Framework\TestCase;

class FormBuilderFormSubmissionActionResultWrapperTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $result = new FormSubmissionActionResultWrapper(
            success: true,
            message: 'All good',
        );

        $this->assertTrue($result->Success);
        $this->assertSame('All good', $result->Message);
        $this->assertEmpty($result->Results);
    }

    public function testFailureResult(): void
    {
        $result = new FormSubmissionActionResultWrapper(
            success: false,
            message: 'Something went wrong',
        );

        $this->assertFalse($result->Success);
        $this->assertSame('Something went wrong', $result->Message);
    }

    public function testResultsArray(): void
    {
        $apiResults = [
            (object)['StatusCode' => 200],
            (object)['StatusCode' => 201],
        ];

        $result = new FormSubmissionActionResultWrapper(
            success: true,
            message: 'Done',
            results: $apiResults,
        );

        $this->assertCount(2, $result->Results);
        $this->assertSame(200, $result->Results[0]->StatusCode);
    }
}
