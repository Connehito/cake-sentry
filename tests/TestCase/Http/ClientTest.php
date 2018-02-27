<?php

namespace Connehito\CakeSentry\Test\TestCase\Http;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Http\Client;
use ReflectionProperty;
use RuntimeException;

class ClientTest extends TestCase
{
    /** @var Client */
    private $subject;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        Configure::write('Sentry.dsn', 'https://user:pass@example.com/yourproject');
        $subject = new Client([]);

        $ravenMock = $this->getMockBuilder(\Raven_Client::class)->getMock();

        $prop = new ReflectionProperty($subject, 'raven');
        $prop->setAccessible(true);
        $prop->setValue($subject, $ravenMock);
        $this->subject = $subject;
    }

    /**
     * test capture exception
     *
     * @covers \Connehito\CakeSentry\Http\Client::capture()
     * @return void
     */
    public function testCaptureException()
    {
        $exception = new RuntimeException('something wrong.');
        $this->subject->getRaven()
            ->expects($this->once())
            ->method('captureException')
            ->with($exception, []);

        $this->subject->capture(
            E_USER_WARNING,
            'some exception',
            ['exception' => $exception]
        );
    }

    /**
     * test capture error
     *
     * @covers \Connehito\CakeSentry\Http\Client::capture()
     * @return void
     */
    public function testCaptureError()
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        $stack = array_slice($stack, 2);
        $data = [
            'level' => E_USER_WARNING,
            'file' => $stack[0]['file'],
            'line' => $stack[0]['line'],
        ];
        $this->subject->getRaven()
            ->expects($this->once())
            ->method('captureMessage')
            ->with('some error', [], $data, $stack);

        $this->subject->capture(
            E_USER_WARNING,
            'some error',
            []
        );
    }

    /**
     * test capture dispatch beforeCapture
     *
     * @return void
     */
    public function testCaptureDispatchBeforeCapture()
    {
        $this->subject->getEventManager()->on(
            'CakeSentry.Client.beforeCapture',
            function () {
                return [
                    'user' => ['id' => 100],
                    'extra' => ['special' => 'yeah!!'],
                ];
            }
        );
        $stack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        $stack = array_slice($stack, 2);
        $data = [
            'level' => E_USER_WARNING,
            'file' => $stack[0]['file'],
            'line' => $stack[0]['line'],
            'user' => ['id' => 100],
            'extra' => ['special' => 'yeah!!'],
        ];

        $this->subject->getRaven()
            ->expects($this->any())
            ->method('captureMessage')
            ->with('some error', [], $data, $stack);

        $this->subject->capture(
            E_USER_WARNING,
            'some error',
            []
        );
    }

    /**
     * test capture dispatch captureError
     *
     * @return void
     */
    public function testCaptureDispatchCaptureError()
    {
        $this->subject->getEventManager()->on(
            'CakeSentry.Client.captureError',
            function (Event $event) {
                $subject = $event->getSubject();
                $subject->lastError = true;
            }
        );

        $this->subject->getRaven()
            ->expects($this->any())
            ->method('captureMessage');
        $this->subject->getRaven()
            ->expects($this->once())
            ->method('getLastError')
            ->willReturn(true);

        $this->subject->capture(
            E_USER_WARNING,
            'some error',
            []
        );

        $this->assertTrue($this->subject->lastError);
    }
}
