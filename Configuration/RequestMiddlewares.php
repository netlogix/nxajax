<?php

declare(strict_types=1);

use Netlogix\Nxajax\Middleware\AcceptHeaderHashBase;
use Netlogix\Nxajax\Middleware\AjaxRequestMiddleware;

return [
    'frontend' => [
        'netlogix/nxajax-json-request' => [
            'target' => AjaxRequestMiddleware::class,
            'after' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
            'before' => ['typo3/cms-frontend/content-length-headers'],
        ],
        'netlogix/nxajax-accept-header-hash-base' => [
            'target' => AcceptHeaderHashBase::class,
            'before' => ['typo3/cms-frontend/tsfe'],
        ],
    ],
];
