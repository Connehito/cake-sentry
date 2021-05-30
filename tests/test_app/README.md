# CakeSentry test app
Instant development this plugin environment.  
You can build stand alone CakePHP server with docker-compose for the following uses.

1. Development the plugin and running unit tests.
2. Playground CakePHP app for the plugin

## Run tests with docker container
1. `dokcer-compose up` in test/test_app.
2. `docker-compose run --rm  test-app bash` and go into container.
3. `composer install` if vendor dir is not created.
4. `vendor/bin/phpunit` to execute tests.

## Sandbox app
Before do it, you must create Sentry PJ and get your DSN.  
Please check Sentry's document about DSN  
https://docs.sentry.io/error-reporting/quickstart/?platform=php#configure-the-sdk

1. `cp tests/test_app/app/config/.env.example tests/test_app/app/app.env`.
2. Set your DSN to `.env`.
3. `docker-compose run --rm  test-app bash` to go into container.
4. `cd /app/tests/test_app & composer install` if vendor dir is not created.
5. exit container.
6. `docker-compose up` to run CakePHP built-in server.
7. Access bellow urls.

### On PHP7 container
You can use `test-app-php7` instead of `test-app`.

### To check error
`http://127.0.0.1:8080/error/:error_level`

Replace `:error_level` to php's defined error you want to check performance.

Like:

* http://127.0.0.1:8080/error/error // trigger E_USER_ERROR
* http://127.0.0.1:8080/error/warning // trigger E_USER_WARNING

If you add `?message` query, then set error message.
Like `http://127.0.0.1:8080/error/notice?message=somthing%20wrong`

### To check exception
`http://127.0.0.1:8080/error/:exception_name/:error_code`

Replace `:exception_name` to exception class's name in defined under \Cake\Http\Exception.Trim `Exception` suffix, and convert name to snake_case style.

Like:

* http://127.0.0.1:8080/exception/not_found/404 // throws NotFoundException(404)
* http://127.0.0.1:8080/exception/not_implemented/503 // throws NotImplementedException(503)

If you add `?message` query, then set error message.
Like `http://127.0.0.1:8080/exception/method_not_allowed/400?message=you%20cant%20access%20here`

### To check log
`http://127.0.0.1:8080/log/:log_level_name`

Replace `:log_level_name` to log level's name.

Like:

* http://127.0.0.1:8080/log/info // log message as LOG_INFO
* http://127.0.0.1:8080/log/notice // log message as LOG_NOTICE

If you add `?message` query, then set error message.
Like `http://127.0.0.1:8080/log/warning?message=some-events-to-logging`
