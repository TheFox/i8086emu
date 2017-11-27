FROM php:7.2-rc-cli
ARG COMPOSER_AUTH

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update && \
	apt-get install -y apt-transport-https rsync ack zlib1g-dev && \
	docker-php-ext-install zip && \
	apt-get clean

# Install Composer.
COPY --from=composer:1.5 /usr/bin/composer /usr/bin/composer

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
