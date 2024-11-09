<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AcceptHeaderHashBase implements MiddlewareInterface
{
    protected array $allowedRequestFormats = ['text/html', 'application/json', 'text/json'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $function = function (array &$params) use ($request): void {
            $params['hashParameters'][self::class] = [];
            $params['hashParameters'][self::class]['REQUEST_METHOD'] = strtoupper((string) $request->getMethod());

            $requestAcceptHeader = strtolower((string) $request->getHeaderLine('accept'));
            $params['hashParameters'][self::class]['HTTP_ACCEPT'] = [];
            foreach ($this->allowedRequestFormats as $allowedAcceptHeader) {
                if (str_contains($requestAcceptHeader, $allowedAcceptHeader)) {
                    $params['hashParameters'][self::class]['HTTP_ACCEPT'][$allowedAcceptHeader] = $allowedAcceptHeader;
                }
            }
        };
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['createHashBase'][self::class] = $function;

        return $handler->handle($request);
    }
}
