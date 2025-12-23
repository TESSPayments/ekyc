FROM phalconphp/cphalcon:v5.9.3-php8.4

# Switch to root for apt + pecl
USER root

# Only install what we absolutely need for xdebug build
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      autoconf \
      gcc \
      g++ \
      make \
      pkg-config \
 && pecl install xdebug \
 && echo "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d/00-xdebug.ini \
 && apt-get purge -y --auto-remove autoconf gcc g++ make pkg-config \
 && rm -rf /var/lib/apt/lists/*

# Custom PHP config
COPY custom-php.ini /usr/local/etc/php/conf.d/custom.ini
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

USER phalcon
WORKDIR /var/www/html
