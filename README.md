# CakePHP Sentry Plugin
CakePHP integration for Sentry.

[![Latest Stable Version](https://poser.pugx.org/connehito/cake-sentry/v/stable)](https://packagist.org/packages/connehito/cake-sentry)
[![Total Downloads](https://poser.pugx.org/connehito/cake-sentry/downloads)](https://packagist.org/packages/connehito/cake-sentry)
[![Build Status](https://travis-ci.org/Connehito/cake-sentry.svg?branch=master)](https://travis-ci.org/Connehito/cake-sentry)
[![codecov](https://codecov.io/gh/connehito/cake-sentry/branch/master/graph/badge.svg)](https://codecov.io/gh/connehito/cake-sentry)
![PHP Code Sniffer](https://github.com/Connehito/cake-sentry/workflows/PHP%20Code%20Sniffer/badge.svg)
[![License](https://poser.pugx.org/connehito/cake-sentry/license)](https://packagist.org/packages/connehito/cake-sentry)

## Requirements
- PHP 7.2+ / PHP 8.0+ 
- CakePHP 4.0+
- and [Sentry](https://sentry.io) account

💡 For CakePHP3.x, use [2.x branch](https://github.com/Connehito/cake-sentry/tree/2.x).

## Installation
### With composer install.
```
composer require connehito/cake-sentry:^3.0
```

If you do not have the ` php-http/async-client-implementation` package, you will need to install it together.  
In that case, you will get a message like the following

```
Problem 1
- sentry/sentry[3.2.0, ... , 3.3.0] require php-http/async-client-implementation ^1.0 -> could not be found in any version, but the following packages provide it:
```

Then, you can use the following command to provide a package such as `symfony/http-client`.

```
composer require connehito/cake-sentry symfony/http-client
```

You can find the available packages on [Packagist](https://packagist.org/providers/php-http/async-client-implementation).


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
bin/cake plugin load Connehito/CakeSentry
```

That's all! :tada:

NOTE:  
If the events(error/exception) don't be captured in Sentry, try changing the order in which the plugins are loaded.  
It is recommended to load this plugin after running `BaseApplication::bootstrap()` and loading other plugins.

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
https://book.cakephp.org/4/en/development/errors.html#error-exception-configuration

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
    public function implementedEvents(): array  
    {
        return [
            'CakeSentry.Client.afterSetup' => 'setServerContext',
        ];
    }

    public function setServerContext(Event $event): void
    {
        /** @var Client $subject */
        $subject = $event->getSubject();
        $options = $subject->getHub()->getClient()->getOptions();

        $options->setEnvironment('test_app');
        $options->setRelease('3.0.0@dev');
    }
}
```

And in `config/bootstrap.php`
```php
\Cake\Event\EventManager::instance()->on(new SentryOptionsContext());
```

### Send more context

Client dispatch `CakeSentry.Client.beforeCapture` event before sending error to sentry.  
You can set context with EventListener.With facade `sentryConfigureScope()` etc, or with `$event->getContext()->getHub()` to access and set context.  
In case you want to handle the information in server request, cake-sentry supports to get `Request` instance in implemented event via `$event->getData('request')`.  

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
    public function implementedEvents(): array
    {
        return [
            'CakeSentry.Client.beforeCapture' => 'setContext',
        ];
    }

    public function setContext(Event $event): void
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
\Cake\Event\EventManager::instance()->on(new SentryErrorContext());
```

### Collecting User feedback
In `CakeSentry.Client.afterCapture` event, you can get last event ID.  
See also [offcial doc](https://docs.sentry.io/enriching-error-data/user-feedback/?platform=php#collecting-feedback).

ex)
```php
class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            'CakeSentry.Client.afterCapture' => 'callbackAfterCapture',
        ];
    }

    public function callbackAfterCapture(Event $event): void
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
