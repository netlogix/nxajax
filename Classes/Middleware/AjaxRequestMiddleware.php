<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Middleware;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class AjaxRequestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routing = $request->getAttribute('routing');
        assert($routing instanceof PageArguments);
        $routeArguments = $routing->getRouteArguments();

        if (
            str_contains((string) $request->getHeaderLine('Accept'), 'application/json')
            && (is_countable($routeArguments) ? count($routeArguments) : 0) > 0
        ) {
            $controller = $request->getAttribute('frontend.controller');
            assert($controller instanceof TypoScriptFrontendController);

            $controller->preparePageContentGeneration($request);
            if (!$controller->tmpl->setup) {
                throw new RuntimeException(
                    'TypoScript not properly loaded, probably due to cached content.',
                    1_593_611_242
                );
            }

            $pluginNamespace = array_key_first($routeArguments);
            [$prefix, $extensionName, $pluginName] = explode('_', (string) $pluginNamespace);
            $pluginSignature = strtolower($extensionName . '_' . $pluginName);
            $typoScriptObjectPath = 'tt_content.list.20.' . $pluginSignature;

            $setup = $this->getPluginTypoScriptConfiguration($controller, $typoScriptObjectPath);
            $typoScriptObjectName = $setup[$pluginSignature];
            $typoScriptObjectConfiguration = $setup[$pluginSignature . '.'];
            $typoScriptObjectConfiguration['controller'] = $routeArguments[$pluginNamespace]['controller'] ?? '';
            $typoScriptObjectConfiguration['action'] = $routeArguments[$pluginNamespace]['action'] ?? '';

            $this->addPluginFormatToRequest($request, $pluginNamespace);

            $contentContentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $contentContentObject->setUserObjectType(ContentObjectRenderer::OBJECTTYPE_USER);
            $pageContent = $contentContentObject->cObjGetSingle(
                $typoScriptObjectName,
                $typoScriptObjectConfiguration,
                $typoScriptObjectPath
            );
            if (str_starts_with((string) $pageContent, '<!--INT_SCRIPT')) {
                $contentContentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
                $contentContentObject->setUserObjectType(ContentObjectRenderer::OBJECTTYPE_USER_INT);
                $pageContent = $contentContentObject->cObjGetSingle(
                    $typoScriptObjectName,
                    $typoScriptObjectConfiguration,
                    $typoScriptObjectPath
                );
                $controller->no_cache = true;
            }

            $controller->addCacheTags(['pageId_' . $controller->id]);
            ObjectAccess::setProperty(
                $controller,
                'cacheExpires',
                GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp'
                ) + $controller->get_cache_timeout()
            );

            $response = new Response();
            $body = new Stream('php://temp', 'rw');
            $body->write($pageContent);

            // @codeCoverageIgnoreStart
            foreach (headers_list() as $headerLine) {
                [$name, $value] = explode(':', $headerLine, 2);
                $response = $response->withHeader($name, explode(',', $value));
            }
            // @codeCoverageIgnoreEnd

            $response = $response->withBody($body);
            $response = $controller->applyHttpHeadersToResponse($response)
                ->withHeader('Content-Length', (string) strlen((string) $pageContent))
                ->withHeader('Content-Type', 'application/json;charset=utf-8');
        } else {
            $response = $handler->handle($request);
        }

        return $response;
    }

    private function getPluginTypoScriptConfiguration(
        TypoScriptFrontendController $controller,
        string $typoScriptObjectPath
    ): array {
        $pathSegments = GeneralUtility::trimExplode('.', $typoScriptObjectPath);
        $lastSegment = array_pop($pathSegments);
        $setup = $controller->tmpl->setup;
        foreach ($pathSegments as $segment) {
            if (!array_key_exists($segment . '.', $setup)) {
                throw new Exception(
                    'TypoScript object path "' . $typoScriptObjectPath . '" does not exist',
                    1_592_475_630
                );
            }
            $setup = $setup[$segment . '.'];
        }
        if (!isset($setup[$lastSegment])) {
            throw new Exception(
                'No Content Object definition found at TypoScript object path "' . $typoScriptObjectPath . '"',
                1_592_475_640
            );
        }

        return $setup;
    }

    private function addPluginFormatToRequest(ServerRequestInterface $request, string $pluginNamespace): void
    {
        $routeArguments = $request->getAttribute('routing');
        assert($routeArguments instanceof PageArguments);
        $arguments = $routeArguments->getArguments();
        $arguments[$pluginNamespace]['format'] = 'json';
        $request = $request->withAttribute(
            'routing',
            new PageArguments(
                $routeArguments->getPageId(),
                $routeArguments->getPageType(),
                $routeArguments->getArguments(),
                $routeArguments->getStaticArguments(),
                $routeArguments->getDynamicArguments(),
            )
        );
        $GLOBALS['TYPO3_REQUEST'] = $request;
    }
}
