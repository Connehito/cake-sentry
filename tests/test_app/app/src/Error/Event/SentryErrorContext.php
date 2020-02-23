<?php

namespace App\Error\Event;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Connehito\CakeSentry\Http\Client;
use Sentry\State\Scope;
use function Sentry\configureScope as sentryConfigureScope;

class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents()
    {
        return [
            'CakeSentry.Client.afterSetup' => 'setServerContext',
            'CakeSentry.Client.beforeCapture' => 'setContext',
            //'CakeSentry.Client.afterCapture' => 'callbackAfterCapture',
        ];
    }

    public function setServerContext(Event $event)
    {
        /** @var Client $subject */
        $subject = $event->getSubject();
        $options = $subject->getHub()->getClient()->getOptions();

        $options->setEnvironment('test_app');
        $options->setRelease('2.0.0@dev');
    }

    public function setContext(Event $event)
    {
        if (PHP_SAPI !== 'cli') {
            /** @var ServerRequest $request */
            $request = $event->getData('request') ?? ServerRequestFactory::fromGlobals();
            $request->trustProxy = true;

            sentryConfigureScope(function (Scope $scope) use ($request, $event) {
                $scope->setTag('app_version',  $request->getHeaderLine('App-Version') ?: 1.0);
                $exception = $event->getData('exception');
                if ($exception) {
                    assert($exception instanceof \Throwable);
                    $scope->setTag('status', $exception->getCode());
                }
                $scope->setUser(['ip_address' => $request->clientIp()]);
                $scope->setExtras([
                    'foo' => 'bar',
                    'request attributes' => $request->getAttributes(),
                ]);
            });
        }
    }

    public function callbackAfterCapture(Event $event)
    {
        $lastEventId = $event->getData('lastEventId');
        // do nothing.
    }
}
