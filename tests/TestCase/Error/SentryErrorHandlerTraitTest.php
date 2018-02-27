<?php

namespace Connehito\CakeSentry\Test\TestCase\Error;

use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Error\SentryErrorHandlerTrait;
use ErrorException;
use Exception;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * Testing stub.
 */
class Stub
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

    protected function _getMessage(Exception $exception)
    {
        return sprintf('[%s]%s', get_class($exception), $exception->getMessage());
    }
}

class SentryErrorHandlerTraitTest extends TestCase
{
    /** @var Stub test subject */
    private $subject;

    /** @var PHPUnit_Framework_MockObject_MockObject LoggerInterface */
    private $logger;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();
        $this->subject = new Stub();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        Log::reset();
        Log::setConfig('error_test', [
            'engine' => $this->logger
        ]);
    }

    /**
     * test for _logError()
     *
     * @return void
     */
    public function testLogError()
    {
        $method = new ReflectionMethod(Stub::class, '_logError');
        $method->setAccessible(true);

        $error = [LOG_NOTICE, ['description' => 'some error', 'code' => 0, 'file' => __FILE__, 'line' => __LINE__]];
        $expected = new ErrorException(
            $error[1]['description'],
            0,
            $error[1]['code'],
            $error[1]['file'],
            $error[1]['line']
        );
        $this->logger->expects($this->once())
            ->method('log')
            ->with('notice', '[ErrorException]some error', ['exception' => $expected, 'scope' => []]);

        $method->invoke($this->subject, $error[0], $error[1]);
    }

    /**
     * test for _logException()
     *
     * @return void
     */
    public function testLogException()
    {
        $method = new ReflectionMethod(Stub::class, '_logException');
        $method->setAccessible(true);

        $exception = new RuntimeException('something wrong.');
        $scope = [];
        $this->logger->expects($this->once())
            ->method('log')
            ->with('error', '[RuntimeException]something wrong.', compact('exception', 'scope'));

        $method->invoke($this->subject, $exception);
    }
}
