FROM php:7.2-cli
ARG COMPOSER_AUTH

ENV DEBIAN_FRONTEND noninteractive
ENV PHP_IDE_CONFIG "serverName=docker"

RUN apt-get update && \
	apt-get install -y apt-transport-https rsync ack git htop zlib1g-dev && \
	docker-php-ext-install zip && \
	apt-get clean

# PHP Setup
ADD docker/php/php.ini /usr/local/etc/php/

# Install Composer.
COPY --from=composer:1.5 /usr/bin/composer /usr/bin/composer

# Install Xdebug.
RUN cd /tmp && \
    git clone git://github.com/xdebug/xdebug.git && \
    cd xdebug && \
    git reset --hard cc6a1d083a3718614e46ab9968d0fe299c26ed07 && \
    phpize && \
    docker-php-ext-configure /tmp/xdebug --enable-xdebug && \
    docker-php-ext-install /tmp/xdebug && \
    cd .. && \
    rm -rf xdebug

ADD docker/php/ext/xdebug.ini /usr/local/etc/php/conf.d/20-xdebug.ini

# Opt Software
ADD opt /opt/app
RUN ls -la /opt
RUN /opt/app/install.sh

# Root App folder
RUN mkdir /app
WORKDIR /app

VOLUME /root/.composer

ENTRYPOINT ["bash"]
