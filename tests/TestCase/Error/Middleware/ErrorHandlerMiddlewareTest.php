<?php

namespace Connehito\CakeSentry\Test\TestCase\Error\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Error\Middleware\ErrorHandlerMiddleware;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ErrorHandlerMiddlewareTest extends TestCase
{
    /** @var PHPUnit_Framework_MockObject_MockObject LoggerInterface */
    private $logger;

    /**
     * setup
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        Log::reset();
        Log::setConfig('error_test', [
            'engine' => $this->logger
        ]);
    }

    /**
     * test error logging called
     *
     * @return void
     */
    public function testLogException()
    {
        $request = ServerRequestFactory::fromGlobals();
        $middleware = new ErrorHandlerMiddleware();
        $response = new Response();

        $exception = new RuntimeException('some exception');
        $this->logger->expects($this->once())
            ->method('log')
            ->with('error');

        $middleware($request, $response, function ($request, $response) use ($exception) {
            throw $exception;
        });
    }

    /**
     * test not logging with exception in skipLog
     *
     * @return void
     */
    public function testLogExceptionSkipLog()
    {
        Configure::write('Error.skipLog', [RuntimeException::class]);

        $request = ServerRequestFactory::fromGlobals();
        $middleware = new ErrorHandlerMiddleware();
        $response = new Response();

        $this->logger->expects($this->never())->method('log');

        $middleware($request, $response, function ($request, $response) {
            throw new RuntimeException('some exception');
        });
    }
}
