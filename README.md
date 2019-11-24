# CakePHP Sentry Plugin
CakePHP integration for Sentry.

[![Latest Stable Version](https://poser.pugx.org/connehito/cake-sentry/v/stable)](https://packagist.org/packages/connehito/cake-sentry)
[![Total Downloads](https://poser.pugx.org/connehito/cake-sentry/downloads)](https://packagist.org/packages/connehito/cake-sentry)
[![Build Status](https://travis-ci.org/Connehito/cake-sentry.svg?branch=master)](https://travis-ci.org/Connehito/cake-sentry)
[![codecov](https://codecov.io/gh/connehito/cake-sentry/branch/master/graph/badge.svg)](https://codecov.io/gh/connehito/cake-sentry)
[![License](https://poser.pugx.org/connehito/cake-sentry/license)](https://packagist.org/packages/connehito/cake-sentry)

## Requirements
- PHP 7.1+
- CakePHP 3.6+
- and [Sentry](https://sentry.io) account


## Installation
### With composer install.
```
composer require connehito/cake-sentry
```

## Usage

### Set config files.
Write your sentry account info.
```php
// in `config/app.php`
return [
  'Sentry' => [
    'dsn' => YOUR_SENTRY_DSN_HERE
  ]
];
```

### Loading plugin.
In Application.php
```php
public function bootstrap()
{
    parent::bootstrap();

    $this->addPlugin(\Connehito\CakeSentry\Plugin::class);
}
```

Or use cake command.
```
bin/cake plugin load Connehito/CakeSentry --bootstrap
```

That's all! :tada:

### Advanced Usage

#### Ignore noisy exceptions
You can filter out exceptions that make a fuss and harder to determine the issues to address(like PageNotFoundException)
Set exceptions not to log in `Error.skipLog`.

ex)
```php
// in `config/app.php`
'Error' => [
    'skipLog' => [
        NotFoundException::class,
        MissingRouteException::class,
        MissingControllerException::class,
    ],
]
```

ref: CakePHP Cookbook  
https://book.cakephp.org/3.0/en/development/errors.html#error-exception-configuration

### Set Options
All configure written in `Configure::write('Sentry')` will be passed to `Sentry\init()`.  
Please check Sentry's official document [about configuration](https://docs.sentry.io/error-reporting/configuration/?platform=php) and [about php-sdk's configuraion](https://docs.sentry.io/platforms/php/#php-specific-options).

In addition to it, CakeSentry provides event hook to set dynamic values to options more easily if you need.
Client dispatch `CakeSentry.Client.afterSetup` event before sending error to sentry.  
Subscribe the event with your logic.

ex)
```php
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;

class SentryOptionsContext implements EventListenerInterface
{
    public function implementedEvents()
    {
        return [
            'CakeSentry.Client.afterSetup' => 'setServerContext',
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
}
```

And in `config/bootstrap.php`
```php
EventManager::instance()->on(new SentryOptionsContext());
```

### Send more context

Client dispatch `CakeSentry.Client.beforeCapture` event before sending error to sentry.  
You can set context with EventListener.With facade `sentryConfigureScope()` etc, or with `$event->getContext()->getHub()` to access and set context.Calling Raven_Client's API or returning values, error context will be sent.
Now, cake-sentry supports to get `Request` instance in implemented event via `$event->getSubject()->getRequest()`.
See also [the section about context in offical doc](https://docs.sentry.io/enriching-error-data/context/?platform=php).

ex)
```php
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Sentry\State\Scope;

use function Sentry\configureScope as sentryConfigureScope;

class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents()
    {
        return [
            'CakeSentry.Client.beforeCapture' => 'setContext',
        ];
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
                    assert($exception instanceof \Exception);
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
}
```

And in `config/bootstrap.php`
```php
EventManager::instance()->on(new SentryErrorContext());
```

### Collecting User feedback
In `CakeSentry.Client.afterCapture` event, you can get last event ID.
See also [offcial doc](https://docs.sentry.io/enriching-error-data/user-feedback/?platform=php#collecting-feedback).

ex)
```php
class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents()
    {
        return [
            'CakeSentry.Client.afterCapture' => 'callbackAfterCapture',
        ];
    }

    public function callbackAfterCapture(Event $event)
    {
        $lastEventId = $event->getData('lastEventId');
    }
}
```

## Contributing
Pull requests and feedback are very welcome :)  
on GitHub at https://github.com/connehito/cake-sentry .

## License
The plugin is available as open source under the terms of the [MIT License](https://github.com/Connehito/cake-sentry/blob/master/LICENSE).
