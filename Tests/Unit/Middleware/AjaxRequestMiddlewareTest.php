<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Tests\Unit\Middleware;

use AssertionError;
use Exception;
use Netlogix\Nxajax\Middleware\AjaxRequestMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AjaxRequestMiddlewareTest extends UnitTestCase implements ContainerInterface
{
    protected bool $resetSingletonInstances = true;
    private array $instances = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->instances = [];
        GeneralUtility::setContainer($this);
    }

    public function tearDown(): void
    {
        $this->instances = [];
        parent::tearDown();
    }

    public function getContainer(): ContainerInterface
    {
        return $this;
    }

    public function get(string $id)
    {
        return $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances);
    }

    public function set(string $id, $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public static function getPluginRenderingResults(): array
    {
        $defaultTypoScriptSetup = [];
        $defaultTypoScriptSetup['tt_content.']['list.']['20.']['foo_bar'] = 'USER';
        $defaultTypoScriptSetup['tt_content.']['list.']['20.']['foo_bar.'] = [];

        return [
            'USER' => [['Plugin content'], $defaultTypoScriptSetup, null],
            'USER with INT_SCRIPT' => [['<!--INT_SCRIPT.42-->', 'Plugin content'], $defaultTypoScriptSetup, null],
            'Missing TypoScript object path segment' => [
                ['Plugin content'],
                [
                    'tt_content.' => ['list.'],
                ],
                Exception::class,
            ],
            'Missing TypoScript object last path segment' => [
                ['Plugin content'],
                [
                    'tt_content.' => [
                        'list.' => [
                            '20.' => [
                                'foo_bar' => null,
                            ],
                        ],
                    ],
                ],
                Exception::class,
            ],
        ];
    }

    #[Test]
    public function process_should_only_forward_request_when_accept_header_does_not_contain_application_json(): void
    {
        $request = $this->getServerRequest(accept: 'text/plain');

        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->getMock();

        $handler->expects(self::once())
            ->method('handle')
            ->with($request);

        $subject = new AjaxRequestMiddleware();
        $subject->process($request, $handler);
    }

    private function getServerRequest(
        int $pageId = 1,
        array $routeArguments = [],
        string $accept = 'application/json',
        ?TypoScriptFrontendController $typoScriptFrontendController = null
    ): ServerRequest {
        $pageArguments = new PageArguments($pageId, 'test', $routeArguments);

        return (new ServerRequest(uri: 'https://example.com', method: 'GET', headers: [
            'accept' => $accept,
        ]))
            ->withAttribute('routing', $pageArguments)
            ->withAttribute('frontend.controller', $typoScriptFrontendController);
    }

    #[Test]
    public function process_should_only_forward_request_when_route_arguments_are_empty(): void
    {
        $request = $this->getServerRequest();

        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->getMock();

        $handler->expects(self::once())
            ->method('handle')
            ->with($request);

        $subject = new AjaxRequestMiddleware();
        $subject->process($request, $handler);
    }

    #[Test]
    public function process_should_throw_assertion_error_when_no_typo_script_frontend_controller_given(): void
    {
        $request = $this->getServerRequest(routeArguments: [
            'tx_foo_bar' => [
                'controller' => 'Bar',
                'action' => 'baz',
            ],
        ]);

        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->getMock();

        $subject = new AjaxRequestMiddleware();

        self::expectException(AssertionError::class);
        $subject->process($request, $handler);
    }

    #[Test]
    public function process_should_throw_runtime_exception_when_typo_script_frontend_controller_is_not_loaded_properly(
    ): void
    {
        $pageId = 1;

        $typoScriptFrontendController = $this->getTypoScriptFrontendController($pageId);

        $request = $this->getServerRequest(
            pageId: $pageId,
            routeArguments: [
                'tx_foo_bar' => [
                    'controller' => 'Bar',
                    'action' => 'baz',
                ],
            ],
            typoScriptFrontendController: $typoScriptFrontendController
        );

        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->getMock();

        $subject = new AjaxRequestMiddleware();

        self::expectException(RuntimeException::class);
        $subject->process($request, $handler);
    }

    private function getTypoScriptFrontendController(int $pageId): TypoScriptFrontendController
    {
        $typoScriptFrontendController = $this->getAccessibleMock(
            originalClassName: TypoScriptFrontendController::class,
            callOriginalConstructor: false
        );

        $typoScriptFrontendController->expects(self::once())
            ->method('preparePageContentGeneration');

        $typoScriptFrontendController->id = $pageId;

        $typoScriptFrontendController->tmpl = $this->getMockBuilder(TemplateService::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $typoScriptFrontendController;
    }

    #[Test]
    #[DataProvider('getPluginRenderingResults')]
    public function process_should_return_plugin_rendering_result_or_fail_with_expected_exception(
        array $renderingResults,
        array $typoScriptSetup,
        string|null $expectedException
    ): void {
        if ($expectedException) {
            self::expectException($expectedException);
        }

        $pageId = 1;

        $typoScriptFrontendController = $this->getTypoScriptFrontendController($pageId);

        $typoScriptFrontendController->tmpl->setup = $typoScriptSetup;

        if (!$expectedException) {
            $typoScriptFrontendController->expects(self::once())
                ->method('applyHttpHeadersToResponse')
                ->willReturnCallback(fn ($response) => $response);
        }

        $request = $this->getServerRequest(
            pageId: $pageId,
            routeArguments: [
                'tx_foo_bar' => [
                    'controller' => 'Bar',
                    'action' => 'baz',
                ],
            ],
            typoScriptFrontendController: $typoScriptFrontendController
        );

        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->getMock();

        if (!$expectedException) {
            $contentObjectRenderer = $this->getMockBuilder(ContentObjectRenderer::class)
                ->disableOriginalConstructor()
                ->getMock();

            $matcher = self::exactly(count($renderingResults));

            $contentObjectRenderer->expects($matcher)
                ->method('cObjGetSingle')
                ->willReturnCallback(fn () => $renderingResults[$matcher->numberOfInvocations() - 1]);

            $this->getContainer()->set(ContentObjectRenderer::class, $contentObjectRenderer);
        }

        $subject = new AjaxRequestMiddleware();
        $response = $subject->process($request, $handler);

        $this->assertEquals('14', $response->getHeaderLine('Content-Length'));
        $this->assertEquals('application/json;charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('Plugin content', $response->getBody()->__toString());
    }
}
