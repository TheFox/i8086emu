FROM php:7.2-cli
ARG COMPOSER_AUTH

ENV DEBIAN_FRONTEND noninteractive
ENV PHP_IDE_CONFIG "serverName=docker"

RUN apt-get update && \
	apt-get install -y apt-transport-https rsync ack git zlib1g-dev && \
	docker-php-ext-install zip && \
	apt-get clean

# Install Composer.
COPY --from=composer:1.5 /usr/bin/composer /usr/bin/composer

# Install Xdebug.
RUN cd /tmp && \
    git clone git://github.com/xdebug/xdebug.git && \
    cd xdebug && \
    git reset --hard 331c3ec9071ba739951530ec6686d67859291f6a && \
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
#ADD . /app

# Install dependencies.
#RUN composer install --no-dev --optimize-autoloader --no-progress --no-suggest --no-interaction
#RUN ls -la

VOLUME /root/.composer
#RUN rm -r /root/.composer/* /root/.composer
#RUN ls -la /root

# Use to store app data, like config files.
#RUN mkdir /data && chmod 777 /data
#VOLUME /data

#VOLUME /mnt
#WORKDIR /mnt

ENTRYPOINT ["bash"]
