<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Middleware;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class AjaxRequestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routing = $request->getAttribute('routing');
        assert($routing instanceof PageArguments);
        $routeArguments = $routing->getRouteArguments();

        if (
            str_contains($request->getHeaderLine('Accept'), 'application/json')
            && (is_countable($routeArguments) ? count($routeArguments) : 0) > 0
        ) {
            $pluginNamespace = array_key_first($routeArguments);
            [$prefix, $extensionName, $pluginName] = explode('_', (string) $pluginNamespace);
            $pluginSignature = strtolower($extensionName . '_' . $pluginName);
            $typoScriptObjectPath = 'tt_content.list.20.' . $pluginSignature;

            $setup = $this->getPluginTypoScriptConfiguration($request, $typoScriptObjectPath);
            $typoScriptObjectName = $setup[$pluginSignature];
            $typoScriptObjectConfiguration = $setup[$pluginSignature . '.'];
            $typoScriptObjectConfiguration['controller'] = $routeArguments[$pluginNamespace]['controller'] ?? '';
            $typoScriptObjectConfiguration['action'] = $routeArguments[$pluginNamespace]['action'] ?? '';

            $request = $this->addPluginFormatToRequest($request, $pluginNamespace);

            $contentContentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $contentContentObject->setRequest($request);
            $pageContent = $contentContentObject->cObjGetSingle(
                $typoScriptObjectName,
                $typoScriptObjectConfiguration,
                $typoScriptObjectPath
            );

            $pageId = $request->getAttribute('frontend.page.information')->getId();
            $pageCacheTag = new CacheTag('pageId_' . $pageId);
            if (str_starts_with((string) $pageContent, '<!--INT_SCRIPT')) {
                $contentContentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
                $contentContentObject->setRequest($request);
                $contentContentObject->setUserObjectType(ContentObjectRenderer::OBJECTTYPE_USER_INT);
                $pageContent = $contentContentObject->cObjGetSingle(
                    $typoScriptObjectName,
                    $typoScriptObjectConfiguration,
                    $typoScriptObjectPath
                );
                $request->getAttribute('frontend.cache.instruction')
                    ->disableCache('EXT:nxajax: Caching disabled using uncached extbase plugin.');
                $pageCacheTag = new CacheTag('pageId_' . $pageId, 0);
            }

            $request->getAttribute('frontend.cache.collector')
                ->addCacheTags($pageCacheTag);

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
            return $request->getAttribute('frontend.controller')
                ->applyHttpHeadersToResponse($request, $response)
                ->withHeader('Content-Length', (string) strlen((string) $pageContent));
        }

        return $handler->handle($request);
    }

    private function getPluginTypoScriptConfiguration(
        ServerRequestInterface $request,
        string $typoScriptObjectPath
    ): array {
        $pathSegments = GeneralUtility::trimExplode('.', $typoScriptObjectPath);
        $lastSegment = array_pop($pathSegments);
        $setup = $request->getAttribute('frontend.typoscript')->getSetupArray();
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

    private function addPluginFormatToRequest(ServerRequestInterface $request, string $pluginNamespace): ServerRequestInterface
    {
        $routeArguments = $request->getAttribute('routing');
        assert($routeArguments instanceof PageArguments);
        $arguments = $routeArguments->getArguments();
        $arguments[$pluginNamespace]['format'] = 'json';
        return $request->withAttribute(
            'routing',
            new PageArguments(
                $routeArguments->getPageId(),
                $routeArguments->getPageType(),
                $routeArguments->getArguments(),
                $routeArguments->getStaticArguments(),
                $routeArguments->getDynamicArguments(),
            )
        );
    }
}
