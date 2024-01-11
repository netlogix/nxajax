<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Tests\Unit\Mvc\Controller;

use Netlogix\Nxajax\Mvc\Controller\AjaxController;
use Netlogix\Nxajax\Mvc\View\JsonView;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Mvc\Controller\Argument;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AjaxControllerTest extends UnitTestCase
{
    #[Test]
    public function defaultViewObjectName_is_set_to_JsonView(): void
    {
        $subject = $this->getAccessibleMock(AjaxController::class, null);
        self::assertSame(JsonView::class, $subject->_get('defaultViewObjectName'));
    }

    #[Test]
    public function errorAction_should_return_no_validation_errors(): void
    {
        $subject = $this->getAccessibleMock(AjaxController::class, null);

        $arguments = new Arguments();

        $subject->_set('arguments', $arguments);
        $subject->_set('view', new JsonView());

        $subject->injectResponseFactory(new ResponseFactory());
        $subject->injectStreamFactory(new StreamFactory());

        $argument = new Argument('foo', 'string');
        $arguments->addArgument($argument);

        $response = $subject->errorAction();
        self::assertSame('{"errors":[]}', $response->getBody()->__toString());
    }

    #[Test]
    public function errorAction_should_return_validation_errors(): void
    {
        $subject = $this->getAccessibleMock(AjaxController::class, null);

        $arguments = new Arguments();

        $subject->_set('arguments', $arguments);
        $subject->_set('view', new JsonView());

        $subject->injectResponseFactory(new ResponseFactory());
        $subject->injectStreamFactory(new StreamFactory());

        $argument = new Argument('foo', 'string');

        $validator = $this->getMockBuilder(ValidatorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $validator->expects(self::once())
            ->method('validate')
            ->willReturnCallback(function (): Result {
                $result = new Result();
                $result
                    ->forProperty('foo')
                    ->addError(new Error('The given subject was invalid.', 123));

                return $result;
            });

        $argument->setValidator($validator);

        $arguments->addArgument($argument);

        $response = $subject->errorAction();
        self::assertSame(
            '{"errors":{"foo":{"foo":[{"propertyName":"foo","message":"The given subject was invalid.","code":123}]}}}',
            $response->getBody()
                ->__toString()
        );
    }

    #[Test]
    public function errorAction_returns_response_with_status_code_400(): void
    {
        $subject = $this->getAccessibleMock(AjaxController::class, null);

        $subject->_set('arguments', new Arguments());
        $subject->_set('view', new JsonView());

        $subject->injectResponseFactory(new ResponseFactory());
        $subject->injectStreamFactory(new StreamFactory());

        $response = $subject->errorAction();
        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function errorAction_returns_response_with_content_type_json_and_utf8(): void
    {
        $subject = $this->getAccessibleMock(AjaxController::class, null);

        $subject->_set('arguments', new Arguments());
        $subject->_set('view', new JsonView());

        $subject->injectResponseFactory(new ResponseFactory());
        $subject->injectStreamFactory(new StreamFactory());

        $response = $subject->errorAction();
        self::assertSame(['application/json; charset=utf-8'], $response->getHeader('Content-Type'));
    }
}
