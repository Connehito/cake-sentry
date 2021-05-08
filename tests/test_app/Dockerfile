FROM php:8-alpine
RUN apk add bash icu-dev git

WORKDIR /usr/src/php/ext
RUN git clone https://github.com/xdebug/xdebug
RUN NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) && \
    docker-php-ext-install -j${NPROC} intl xdebug pdo_mysql
RUN echo -e "zend_extension=xdebug.so\n" \
        "xdebug.client_host=host.docker.internal\n" \
        "xdebug.mode=debug" \
    > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo -e "assert.exception=1" \
    > /usr/local/etc/php/conf.d/assert.ini

COPY tests/test_app/composer-install.sh /usr/bin/composer-install
RUN composer-install \
  && mv composer.phar /usr/bin/composer
WORKDIR /app
