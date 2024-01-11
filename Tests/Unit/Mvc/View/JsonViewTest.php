<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Tests\Unit\Mvc\View;

use Netlogix\Nxajax\Mvc\View\JsonView;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class JsonViewTest extends UnitTestCase
{
    #[Test]
    public function expose_settings_is_false(): void
    {
        $subject = $this->getAccessibleMock(JsonView::class, null);
        self::assertFalse($subject->_get('exposeSettings'));
    }

    #[Test]
    public function expose_settings_are_settable_to_true(): void
    {
        $subject = $this->getAccessibleMock(JsonView::class, null);
        $subject->setExposeSettings(true);
        self::assertTrue($subject->_get('exposeSettings'));
    }

    #[Test]
    public function settings_are_not_exposed_by_default(): void
    {
        $subject = new JsonView();
        $subject->assign('settings', [
            'alice' => 'bob',
        ]);
        $subject->assign('foo', 'eve');
        $subject->assign('bar', 'baz');
        self::assertSame('{"foo":"eve","bar":"baz"}', $subject->render());
    }

    #[Test]
    public function settings_are_exposed_if_expose_settings_is_set_to_true(): void
    {
        $subject = new JsonView();
        $subject->setExposeSettings(true);
        $subject->assign('settings', [
            'alice' => 'bob',
        ]);
        $subject->assign('foo', 'bar');
        self::assertSame('{"settings":{"alice":"bob"},"foo":"bar"}', $subject->render());
    }

    #[Test]
    public function single_value_is_returned_if_only_one_value_is_assigned(): void
    {
        $subject = new JsonView();
        $subject->assign('foo', 'bar');
        self::assertSame('"bar"', $subject->render());
    }

    #[Test]
    public function query_result_is_converted_to_array(): void
    {
        $queryResult = $this->getMockBuilder(QueryResultInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $queryResult->expects(self::once())
            ->method('toArray')
            ->willReturn([]);

        $subject = new JsonView();
        $subject->assign('result', $queryResult);
        self::assertSame('[]', $subject->render());
    }

    #[Test]
    public function assign_adds_key_value_to_variables(): void
    {
        $subject = $this->getAccessibleMock(JsonView::class, null);
        $subject->assign('foo', 'bar');
        self::assertSame([
            'foo' => 'bar',
        ], $subject->_get('variables'));
    }

    #[Test]
    public function assign_multiple_adds_key_value_to_variables(): void
    {
        $subject = $this->getAccessibleMock(JsonView::class, null);
        $subject->assignMultiple([
            'foo' => 'bar',
            'baz' => 'qux',
        ]);
        self::assertSame([
            'foo' => 'bar',
            'baz' => 'qux',
        ], $subject->_get('variables'));
    }

    #[Test]
    public function render_section_does_nothing(): void
    {
        // This test is not really needed, but for the sake of completeness... (and 100% coverage)
        $subject = new JsonView();
        self::assertNull($subject->renderSection('foo'));
    }

    #[Test]
    public function render_partial_does_nothing(): void
    {
        // This test is not really needed, but for the sake of completeness... (and 100% coverage)
        $subject = new JsonView();
        self::assertNull($subject->renderPartial('foo', 'bar', []));
    }
}
