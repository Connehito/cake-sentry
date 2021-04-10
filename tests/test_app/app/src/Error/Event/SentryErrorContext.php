<?php
declare(strict_types=1);

namespace TestApp\Error\Event;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Connehito\CakeSentry\Http\Client;
use Sentry\State\Scope;
use function Sentry\configureScope as sentryConfigureScope;

class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            'CakeSentry.Client.afterSetup' => 'setServerContext',
            'CakeSentry.Client.beforeCapture' => 'setContext',
            //'CakeSentry.Client.afterCapture' => 'callbackAfterCapture',
        ];
    }

    public function setServerContext(Event $event): void
    {
        /** @var Client $subject */
        $subject = $event->getSubject();
        $options = $subject->getHub()->getClient()->getOptions();

        $options->setEnvironment('test_app');
        $options->setRelease('2.0.0@dev');
    }

    public function setContext(Event $event): void
    {
        if (PHP_SAPI !== 'cli') {
            /** @var ServerRequest $request */
            $request = $event->getData('request') ?? ServerRequestFactory::fromGlobals();
            $request->trustProxy = true;

            sentryConfigureScope(function (Scope $scope) use ($request, $event) {
                $scope->setTag('app_version', $request->getHeaderLine('App-Version') ?: '1.0');
                $scope->setTag('cakephp_version', Configure::version());
                $exception = $event->getData('exception');
                if ($exception) {
                    assert($exception instanceof \Throwable);
                    $scope->setTag('status', (string)$exception->getCode());
                }
                $scope->setUser(['ip_address' => $request->clientIp()]);
                $scope->setExtras([
                    'foo' => 'bar',
                    'request attributes' => $request->getAttributes(),
                ]);
            });
        }
    }

    public function callbackAfterCapture(Event $event): void
    {
        $lastEventId = $event->getData('lastEventId');
        // do nothing.
    }
}
