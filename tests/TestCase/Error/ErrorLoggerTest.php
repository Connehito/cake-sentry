<?php

namespace Connehito\CakeSentry\Test\TestCase\Error;

use Cake\Core\Configure;
use Cake\Http\ServerRequestFactory;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Error\ErrorLogger;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class ErrorLoggerTest extends TestCase
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
    public function testLogException(): void
    {
        $this->logger->expects($this->once())
            ->method('log')
            ->with('error');

        $middleware = new ErrorLogger(Configure::read('Error', []));
        $middleware->log(new RuntimeException('some exception'), ServerRequestFactory::fromGlobals());
    }

    /**
     * test not logging with exception in skipLog
     *
     * @return void
     */
    public function testLogExceptionSkipLog(): void
    {
        Configure::write('Error.skipLog', [RuntimeException::class]);
        Configure::write('App.paths.templates', [
            TEST_APP . 'app' . DS . 'templates' . DS,
            ROOT . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS . 'templates' . DS,
        ]);

        $this->logger->expects($this->never())
             ->method('log');

        $middleware = new ErrorLogger(Configure::read('Error', []));
        $middleware->log(new RuntimeException('some exception'), ServerRequestFactory::fromGlobals());
    }
}
