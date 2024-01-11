<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Tests\Unit\Middleware;

use Netlogix\Nxajax\Middleware\AcceptHeaderHashBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AcceptHeaderHashBaseTest extends UnitTestCase
{
    public static function getAllowedRequestFormats(): array
    {
        return [['text/html'], ['application/json'], ['text/json']];
    }

    public function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']);
    }

    #[Test]
    public function allowedRequestFormats_is_set_to_html_and_json(): void
    {
        $subject = $this->getAccessibleMock(AcceptHeaderHashBase::class, null);
        self::assertEquals(['text/html', 'application/json', 'text/json'], $subject->_get('allowedRequestFormats'));
    }

    #[Test]
    public function process_adds_hook_to_createHashBase(): void
    {
        $subject = new AcceptHeaderHashBase();

        $request = new ServerRequest('https://example.com', 'GET');

        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->getMock();

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['createHashBase'] = [];

        $subject->process($request, $handler);

        self::assertArrayHasKey(
            AcceptHeaderHashBase::class,
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['createHashBase']
        );
    }

    #[Test]
    public function hashParameters_should_contain_http_method(): void
    {
        $httpMethod = 'GET';

        $request = new ServerRequest('https://example.com', $httpMethod);
        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->getMock();

        $subject = new AcceptHeaderHashBase();
        $subject->process($request, $handler);

        $params = [];

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['createHashBase'][AcceptHeaderHashBase::class](
            $params
        );

        self::assertEquals($httpMethod, $params['hashParameters'][AcceptHeaderHashBase::class]['REQUEST_METHOD']);
    }

    #[Test]
    #[DataProvider('getAllowedRequestFormats')]
    public function hashParameters_should_contain_accept_header_if_allowed(string $acceptHeader): void
    {
        $request = new ServerRequest(uri: 'https://example.com', method: 'GET', headers: [
            'accept' => $acceptHeader,
        ]);

        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->getMock();

        $subject = new AcceptHeaderHashBase();
        $subject->process($request, $handler);

        $params = [];

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['createHashBase'][AcceptHeaderHashBase::class](
            $params
        );

        self::assertArrayHasKey(
            $acceptHeader,
            $params['hashParameters'][AcceptHeaderHashBase::class]['HTTP_ACCEPT']
        );
        self::assertEquals(
            $acceptHeader,
            $params['hashParameters'][AcceptHeaderHashBase::class]['HTTP_ACCEPT'][$acceptHeader]
        );
    }

    #[Test]
    public function hashParameters_should_not_contain_accept_header_if_not_allowed(): void
    {
        $request = new ServerRequest(uri: 'https://example.com', method: 'GET', headers: [
            'accept' => 'text/plain',
        ]);

        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->getMock();

        $subject = new AcceptHeaderHashBase();
        $subject->process($request, $handler);

        $params = [];

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['createHashBase'][AcceptHeaderHashBase::class](
            $params
        );

        self::assertEmpty($params['hashParameters'][AcceptHeaderHashBase::class]['HTTP_ACCEPT']);
    }
}
