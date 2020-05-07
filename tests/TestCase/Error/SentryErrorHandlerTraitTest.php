<?php

namespace Connehito\CakeSentry\Test\TestCase\Error;

use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Error\SentryErrorHandlerTrait;
use ErrorException;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * Testing stub.
 */
final class Stub
{
    use SentryErrorHandlerTrait;

    /**
     * Options for this instance.
     *
     * @var array
     */
    protected $_options = [
        'log' => true,
    ];

    /** @var array */
    protected $_defaultConfig = [];

    public $logExceptionCalledWithParams = null;

    protected function logException(Throwable $exception, ?ServerRequestInterface $request = null)
    {
        $this->logExceptionCalledWithParams = [$exception, $request];
        return true;
    }
}

final class SentryErrorHandlerTraitTest extends TestCase
{
    /** @var Stub test subject */
    private $subject;

    /** @var MockObject LoggerInterface */
    private $logger;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->subject = new Stub();
    }

    /**
     * test for _logError()
     *
     * @return void
     */
    public function testLogError(): void
    {
        $method = new ReflectionMethod(Stub::class, '_logError');
        $method->setAccessible(true);

        $error = [LOG_NOTICE, ['description' => 'some error', 'code' => 0, 'file' => __FILE__, 'line' => __LINE__]];

        $result = $method->invoke($this->subject, $error[0], $error[1]);

        static::assertTrue($result);
        static::assertNotNull($this->subject->logExceptionCalledWithParams);

        /** @var ErrorException $exception */
        [$exception, $request] = $this->subject->logExceptionCalledWithParams;
        static::assertEquals($error[1]['description'], $exception->getMessage());
        static::assertEquals(0, $exception->getCode());
        static::assertEquals($error[1]['code'], $exception->getSeverity());
        static::assertEquals($error[1]['file'], $exception->getFile());
        static::assertEquals($error[1]['line'], $exception->getLine());
        static::assertNull($request);
    }
}
