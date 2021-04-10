FROM php:7.4-alpine
RUN apk add bash icu-dev git

WORKDIR /usr/src/php/ext
RUN git clone https://github.com/xdebug/xdebug
RUN NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) && \
    docker-php-ext-install -j${NPROC} intl xdebug pdo_mysql
RUN echo -e "zend_extension = xdebug.so\n" \
        "xdebug.remote_enable = On\n" \
        "xdebug.remote_autostart = On\n" \
        "xdebug.remote_host = host.docker.internal\n" \
        "xdebug.idekey = CAKE_SENTRY_TEST_APP\n" \
    > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY tests/test_app/composer-install.sh /usr/bin/composer-install
RUN composer-install \
  && mv composer.phar /usr/bin/composer
WORKDIR /app
