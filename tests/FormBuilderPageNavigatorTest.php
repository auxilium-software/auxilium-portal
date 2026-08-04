<?php

use Auxilium\FormHandling\PageNavigator;
use PHPUnit\Framework\TestCase;

class FormBuilderPageNavigatorTest extends TestCase
{
    private PageNavigator $navigator;

    protected function setUp(): void
    {
        $this->navigator = new PageNavigator();
    }

    // <editor-fold defaultstate="collapsed" desc="buildVisiblePagesList">
    public function testBuildVisiblePagesListAllVisible(): void
    {
        $pages = [
            ['id' => 'p1', 'title' => 'Page 1'],
            ['id' => 'p2', 'title' => 'Page 2'],
            ['id' => 'p3', 'title' => 'Page 3'],
        ];

        $result = $this->navigator->buildVisiblePagesList($pages, []);

        $this->assertCount(3, $result['visiblePages']);
        $this->assertSame([0, 1, 2], $result['pageIndexMap']);
    }

    public function testBuildVisiblePagesListWithRenderIfFalse(): void
    {
        $pages = [
            ['id' => 'p1', 'title' => 'Page 1'],
            ['id' => 'p2', 'title' => 'Page 2', 'renderIf' => 'false()'],
            ['id' => 'p3', 'title' => 'Page 3'],
        ];

        $result = $this->navigator->buildVisiblePagesList($pages, []);

        $this->assertCount(2, $result['visiblePages']);
        $this->assertSame([0, 2], $result['pageIndexMap']);
        $this->assertSame('p1', $result['visiblePages'][0]['id']);
        $this->assertSame('p3', $result['visiblePages'][1]['id']);
    }

    public function testBuildVisiblePagesListWithVariableCondition(): void
    {
        $pages = [
            ['id' => 'p1', 'title' => 'Page 1'],
            ['id' => 'p2', 'title' => 'Page 2', 'renderIf' => 'exists($formData["show_extra"])'],
        ];

        $varsWithField = ['formData' => ['show_extra' => 'yes']];
        $result = $this->navigator->buildVisiblePagesList($pages, $varsWithField);
        $this->assertCount(2, $result['visiblePages']);

        $varsWithoutField = ['formData' => []];
        $result = $this->navigator->buildVisiblePagesList($pages, $varsWithoutField);
        $this->assertCount(1, $result['visiblePages']);
        $this->assertSame('p1', $result['visiblePages'][0]['id']);
    }

    public function testBuildVisiblePagesListAllHidden(): void
    {
        $pages = [
            ['id' => 'p1', 'renderIf' => 'false()'],
            ['id' => 'p2', 'renderIf' => 'false()'],
        ];

        $result = $this->navigator->buildVisiblePagesList($pages, []);

        $this->assertEmpty($result['visiblePages']);
        $this->assertEmpty($result['pageIndexMap']);
    }

    public function testBuildVisiblePagesListEmptyPages(): void
    {
        $result = $this->navigator->buildVisiblePagesList([], []);

        $this->assertEmpty($result['visiblePages']);
        $this->assertEmpty($result['pageIndexMap']);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="findNextVisiblePage">
    public function testFindNextVisiblePageForward(): void
    {
        $pages = [
            ['id' => 'p1'],
            ['id' => 'p2'],
            ['id' => 'p3'],
        ];

        $result = $this->navigator->findNextVisiblePage($pages, 0, 1, []);
        $this->assertSame(1, $result);
    }

    public function testFindNextVisiblePageForwardSkipsHidden(): void
    {
        $pages = [
            ['id' => 'p1'],
            ['id' => 'p2', 'renderIf' => 'false()'],
            ['id' => 'p3'],
        ];

        $result = $this->navigator->findNextVisiblePage($pages, 0, 1, []);
        $this->assertSame(2, $result);
    }

    public function testFindNextVisiblePageBackward(): void
    {
        $pages = [
            ['id' => 'p1'],
            ['id' => 'p2'],
            ['id' => 'p3'],
        ];

        $result = $this->navigator->findNextVisiblePage($pages, 2, -1, []);
        $this->assertSame(1, $result);
    }

    public function testFindNextVisiblePageBackwardSkipsHidden(): void
    {
        $pages = [
            ['id' => 'p1'],
            ['id' => 'p2', 'renderIf' => 'false()'],
            ['id' => 'p3'],
        ];

        $result = $this->navigator->findNextVisiblePage($pages, 2, -1, []);
        $this->assertSame(0, $result);
    }

    public function testFindNextVisiblePageReturnsNegativeOneWhenNoneFound(): void
    {
        $pages = [
            ['id' => 'p1'],
        ];

        $result = $this->navigator->findNextVisiblePage($pages, 0, 1, []);
        $this->assertSame(-1, $result);
    }

    public function testFindNextVisiblePageAllRemainingHidden(): void
    {
        $pages = [
            ['id' => 'p1'],
            ['id' => 'p2', 'renderIf' => 'false()'],
            ['id' => 'p3', 'renderIf' => 'false()'],
        ];

        $result = $this->navigator->findNextVisiblePage($pages, 0, 1, []);
        $this->assertSame(-1, $result);
    }

    public function testFindNextVisiblePageBackwardFromFirstPage(): void
    {
        $pages = [
            ['id' => 'p1'],
            ['id' => 'p2'],
        ];

        $result = $this->navigator->findNextVisiblePage($pages, 0, -1, []);
        $this->assertSame(-1, $result);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="getCurrentVisiblePageIndex">
    public function testGetCurrentVisiblePageIndexFound(): void
    {
        $pageIndexMap = [0, 2, 4]; // visible pages map to actual indices 0, 2, 4

        $result = $this->navigator->getCurrentVisiblePageIndex(2, $pageIndexMap);
        $this->assertSame(1, $result);
    }

    public function testGetCurrentVisiblePageIndexFirstPage(): void
    {
        $pageIndexMap = [0, 1, 2];

        $result = $this->navigator->getCurrentVisiblePageIndex(0, $pageIndexMap);
        $this->assertSame(0, $result);
    }

    public function testGetCurrentVisiblePageIndexNotFoundDefaultsToZero(): void
    {
        $pageIndexMap = [0, 2, 4];

        $result = $this->navigator->getCurrentVisiblePageIndex(3, $pageIndexMap);
        $this->assertSame(0, $result);
    }

    public function testGetCurrentVisiblePageIndexEmptyMap(): void
    {
        $result = $this->navigator->getCurrentVisiblePageIndex(0, []);
        $this->assertSame(0, $result);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="findPageIndexById">
    public function testFindPageIndexByIdFound(): void
    {
        $pages = [
            ['id' => 'page_one'],
            ['id' => 'page_two'],
            ['id' => 'page_three'],
        ];

        $result = $this->navigator->findPageIndexById('page_two', $pages);
        $this->assertSame(1, $result);
    }

    public function testFindPageIndexByIdNotFound(): void
    {
        $pages = [
            ['id' => 'page_one'],
            ['id' => 'page_two'],
        ];

        $result = $this->navigator->findPageIndexById('nonexistent', $pages);
        $this->assertNull($result);
    }

    public function testFindPageIndexByIdEmptyPages(): void
    {
        $result = $this->navigator->findPageIndexById('anything', []);
        $this->assertNull($result);
    }

    public function testFindPageIndexByIdFirstMatch(): void
    {
        $pages = [
            ['id' => 'duplicate'],
            ['id' => 'duplicate'],
        ];

        $result = $this->navigator->findPageIndexById('duplicate', $pages);
        $this->assertSame(0, $result);
    }
    // </editor-fold>
}
