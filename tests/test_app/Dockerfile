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

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php -r "if (hash_file('sha384', 'composer-setup.php') === 'baf1608c33254d00611ac1705c1d9958c817a1a33bce370c0595974b342601bd80b92a3f46067da89e3b06bff421f182') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
  && php composer-setup.php \
  && mv composer.phar /usr/bin/composer
WORKDIR /app
