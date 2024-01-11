<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Mvc\Controller;

use Netlogix\Nxajax\Mvc\View\JsonView;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController as ExtbaseActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Argument;

class AjaxController extends ExtbaseActionController
{
    protected $defaultViewObjectName = JsonView::class;

    public function errorAction(): ResponseInterface
    {
        $result = [];
        foreach ($this->arguments->getIterator() as $argument) {
            assert($argument instanceof Argument);
            $validationResult = $argument->validate();
            if (!$validationResult->hasErrors()) {
                continue;
            }
            $flattenErrors = $validationResult->getFlattenedErrors();
            foreach ($flattenErrors as $fullQualifiedPropertyPath => $errors) {
                [$propertyName] = explode('.', (string) $fullQualifiedPropertyPath, 2);
                foreach ($errors as $error) {
                    assert($error instanceof Error);
                    $result[$argument->getName()][$propertyName][] = [
                        'propertyName' => $propertyName,
                        'message' => $error->render(),
                        'code' => $error->getCode(),
                    ];
                }
            }
        }
        $this->view->assign('errors', [
            'errors' => $result,
        ]);

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(400)
            ->withBody($this->streamFactory->createStream($this->view->render()));
    }
}
