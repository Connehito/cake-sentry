<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Test\TestCase\Error;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
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
    private $subject;

    /**
     * setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->getMockBuilder(LoggerInterface::class)->getMock();
        Log::reset();
        Log::setConfig('error_test', ['engine' => $this->subject]);
    }

    /**
     * test error logging called
     *
     * @return void
     */
    public function testLog(): void
    {
        $thrownException = new RuntimeException('some exception');

        $this->subject->expects($this->once())
            ->method('log')
            ->with(
                'error',
                $this->stringContains('some exception'),
                $this->callback(function ($args) use ($thrownException) {
                    return $args['exception'] === $thrownException &&
                        $args['request'] instanceof ServerRequest;
                })
            );

        $logger = new ErrorLogger([]);
        $logger->log($thrownException, ServerRequestFactory::fromGlobals());
    }

    /**
     * test not logging with exception in skipLog
     *
     * @return void
     */
    public function testLogSkipLog(): void
    {
        Configure::write('Error.skipLog', [RuntimeException::class]);

        $this->subject->expects($this->never())->method('log');

        $middleware = new ErrorLogger(Configure::readOrFail('Error'));
        $middleware->log(new RuntimeException('some exception'), ServerRequestFactory::fromGlobals());
    }
}
