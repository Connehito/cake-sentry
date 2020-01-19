<?php

namespace Connehito\CakeSentry\Test\TestCase\Error\Middleware;

use Cake\Core\Configure;
use Cake\Http\ServerRequestFactory;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Error\Middleware\ErrorHandlerMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;
use App\Http\TestRequestHandler;

final class ErrorHandlerMiddlewareTest extends TestCase
{
    /** @var MockObject LoggerInterface */
    private $logger;

    /**
     * setup
     *
     * @return void
     */
    public function setUp(): void
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
        Configure::write('App.paths.templates', [
            TEST_APP . 'app' . DS . 'templates' . DS,
            ROOT . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS . 'templates' . DS,
        ]);

        $request = ServerRequestFactory::fromGlobals();
        $testHandler = new TestRequestHandler(function($req){
            throw new RuntimeException('some exception');
        });

        $this->logger->expects($this->once())
            ->method('log')
            ->with('error');

        $middleware = new ErrorHandlerMiddleware(Configure::read('Error', []));
        $middleware->process($request, $testHandler);
    }

    /**
     * test not logging with exception in skipLog
     *
     * @return void
     */
    public function testLogExceptionSkipLog()
    {
        Configure::write('Error.skipLog', [RuntimeException::class]);
        Configure::write('App.paths.templates', [
            TEST_APP . 'app' . DS . 'templates' . DS,
            ROOT . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS . 'templates' . DS,
        ]);

        $request = ServerRequestFactory::fromGlobals();
        $testHandler = new TestRequestHandler(function($req){
            throw new RuntimeException('some exception');
        });

        $this->logger->expects($this->never())
             ->method('log');

        $middleware = new ErrorHandlerMiddleware(Configure::read('Error', []));
        $middleware->process($request, $testHandler);
    }
}
