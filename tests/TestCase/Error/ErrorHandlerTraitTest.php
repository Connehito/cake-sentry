<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Test\TestCase\Error;

use Cake\Error\ErrorHandler;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Error\ErrorHandlerTrait;
use ErrorException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ErrorHandlerTraitTest extends TestCase
{
    /**
     * test for _logError()
     *
     * @return void
     */
    public function testLogError(): void
    {
        $errorSeverity = E_USER_WARNING;
        $errorMessage = 'some error';
        $errorFile = __FILE__;
        $errorLine = __LINE__;
        $subject = $this->getSubject();

        $subject->handleError($errorSeverity, $errorMessage, $errorFile, $errorLine);

        /** @var ErrorException $actual */
        $actual = $subject->wrappedException;

        $this->assertInstanceOf(ErrorException::class, $actual);
        $this->assertSame($errorMessage, $actual->getMessage());
        $this->assertSame(0, $actual->getCode());
        $this->assertSame($errorSeverity, $actual->getSeverity());
        $this->assertSame($errorFile, $actual->getFile());
        $this->assertSame($errorLine, $actual->getLine());
    }

    /**
     * Get instance of ErrorHandlerTrait implementation
     */
    private function getSubject()
    {
        $subject = new class extends ErrorHandler
        {
            use ErrorHandlerTrait {
                _logError as originalLogError;
            }

            public $wrappedException;

            /**
             * {@inheritDoc}
             * Implement a non-functional method to avoid echoing in running test.
             */
            protected function _displayError(array $error, bool $debug): void
            {
                // do nothing
            }

            public function logException(Throwable $exception, ?ServerRequestInterface $request = null): bool
            {
                $this->wrappedException = $exception;

                return false;
            }
        };

        return $subject;
    }
}
