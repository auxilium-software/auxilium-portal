<?php

use Auxilium\FormHandling\ReviewPageBuilder;
use PHPUnit\Framework\TestCase;

class FormBuilderReviewPageBuilderTest extends TestCase
{
    private ReviewPageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ReviewPageBuilder();
    }

    // <editor-fold defaultstate="collapsed" desc="getVisibleComponents">
    public function testGetVisibleComponentsNoReviewPage(): void
    {
        $formSpec = [];
        $result = $this->builder->getVisibleComponents($formSpec, []);

        $this->assertEmpty($result);
    }

    public function testGetVisibleComponentsNoComponents(): void
    {
        $formSpec = ['reviewPage' => []];
        $result = $this->builder->getVisibleComponents($formSpec, []);

        $this->assertEmpty($result);
    }

    public function testGetVisibleComponentsAllVisible(): void
    {
        $formSpec = [
            'reviewPage' => [
                'components' => [
                    'component' => [
                        ['type' => 'TEXT', 'value' => 'Hello'],
                        ['type' => 'TEXT', 'value' => 'World'],
                    ],
                ],
            ],
        ];

        $result = $this->builder->getVisibleComponents($formSpec, ['formData' => []]);

        $this->assertCount(2, $result);
    }

    public function testGetVisibleComponentsFiltersOnIfCondition(): void
    {
        $formSpec = [
            'reviewPage' => [
                'components' => [
                    'component' => [
                        ['type' => 'TEXT', 'value' => 'Always shown'],
                        ['type' => 'TEXT', 'value' => 'Never shown', 'if' => 'false()'],
                        ['type' => 'TEXT', 'value' => 'Also shown'],
                    ],
                ],
            ],
        ];

        $result = $this->builder->getVisibleComponents($formSpec, ['formData' => []]);

        $this->assertCount(2, $result);
        $this->assertSame('Always shown', $result[0]['value']);
        $this->assertSame('Also shown', $result[1]['value']);
    }

    public function testGetVisibleComponentsEvaluatesDynamicValue(): void
    {
        $formSpec = [
            'reviewPage' => [
                'components' => [
                    'component' => [
                        ['type' => 'TEXT', 'value' => '$formData["name"]'],
                    ],
                ],
            ],
        ];

        $vars = ['formData' => ['name' => 'Cerys']];
        $result = $this->builder->getVisibleComponents($formSpec, $vars);

        $this->assertCount(1, $result);
        $this->assertSame('Cerys', $result[0]['value']);
    }

    public function testGetVisibleComponentsStaticValueUntouched(): void
    {
        $formSpec = [
            'reviewPage' => [
                'components' => [
                    'component' => [
                        ['type' => 'TEXT', 'value' => 'No dollar sign here'],
                    ],
                ],
            ],
        ];

        $result = $this->builder->getVisibleComponents($formSpec, ['formData' => []]);

        $this->assertSame('No dollar sign here', $result[0]['value']);
    }

    public function testGetVisibleComponentsConditionalOnVariable(): void
    {
        $formSpec = [
            'reviewPage' => [
                'components' => [
                    'component' => [
                        [
                            'type' => 'TEXT',
                            'value' => 'Extra info',
                            'if' => 'exists($formData["extra"])',
                        ],
                    ],
                ],
            ],
        ];

        $varsWithExtra = ['formData' => ['extra' => 'yes']];
        $result = $this->builder->getVisibleComponents($formSpec, $varsWithExtra);
        $this->assertCount(1, $result);

        $varsWithoutExtra = ['formData' => []];
        $result = $this->builder->getVisibleComponents($formSpec, $varsWithoutExtra);
        $this->assertEmpty($result);
    }

    public function testGetVisibleComponentsDescriptionListProcessed(): void
    {
        $formSpec = [
            'reviewPage' => [
                'components' => [
                    'component' => [
                        [
                            'type' => 'DESCRIPTION_LIST',
                            'dictionary' => [
                                'item' => [
                                    ['key' => 'Name', 'value' => 'Cerys'],
                                    ['key' => 'Role', 'value' => 'Developer'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->builder->getVisibleComponents($formSpec, ['formData' => []]);

        $this->assertCount(1, $result);
        $items = $result[0]['dictionary']['item'];
        $this->assertCount(2, $items);
        $this->assertSame('Name', $items[0]['key']);
        $this->assertSame('Cerys', $items[0]['value']);
    }

    public function testGetVisibleComponentsDescriptionListWithDynamicValues(): void
    {
        $formSpec = [
            'reviewPage' => [
                'components' => [
                    'component' => [
                        [
                            'type' => 'DESCRIPTION_LIST',
                            'dictionary' => [
                                'item' => [
                                    ['key' => 'Email', 'value' => '$formData["email"]'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $vars = ['formData' => ['email' => 'cerys@example.com']];
        $result = $this->builder->getVisibleComponents($formSpec, $vars);

        $items = $result[0]['dictionary']['item'];
        $this->assertSame('cerys@example.com', $items[0]['value']);
    }

    public function testGetVisibleComponentsDescriptionListSingleItem(): void
    {
        $formSpec = [
            'reviewPage' => [
                'components' => [
                    'component' => [
                        [
                            'type' => 'DESCRIPTION_LIST',
                            'dictionary' => [
                                'item' => ['key' => 'Name', 'value' => 'Cerys'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->builder->getVisibleComponents($formSpec, ['formData' => []]);

        $items = $result[0]['dictionary']['item'];
        $this->assertCount(1, $items);
        $this->assertSame('Name', $items[0]['key']);
    }

    public function testGetVisibleComponentsDescriptionListFlatArray(): void
    {
        $formSpec = [
            'reviewPage' => [
                'components' => [
                    'component' => [
                        [
                            'type' => 'DESCRIPTION_LIST',
                            'dictionary' => [
                                'item' => ['Name', 'Cerys', 'Role', 'Developer'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->builder->getVisibleComponents($formSpec, ['formData' => []]);

        $items = $result[0]['dictionary']['item'];
        $this->assertCount(2, $items);
        $this->assertSame('Name', $items[0]['key']);
        $this->assertSame('Cerys', $items[0]['value']);
        $this->assertSame('Role', $items[1]['key']);
        $this->assertSame('Developer', $items[1]['value']);
    }
    // </editor-fold>
}
