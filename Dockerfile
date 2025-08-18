FROM debian:bookworm
WORKDIR /app

SHELL ["/bin/bash", "-c"]

# install packages
RUN apt-get update
RUN apt-get -y install supervisor wget grep curl openjdk-17-jre-headless
RUN apt-get -y install nginx
RUN apt-get -y install mariadb-server mariadb-client
RUN apt-get -y install php8.2 php8.2-fpm
RUN apt-get -y install php8.2-gd php8.2-mysql php8.2-simplexml php8.2-mysql php8.2-curl php8.2-bcmath php-json php8.2-imap php8.2-mbstring php8.2-zip
RUN apt-get -y install composer ssl-cert git jq
RUN apt-get -y install iputils-ping nano

#RUN export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`; a2enmod php$PHP_VER;
#RUN a2enmod headers
#RUN a2enmod rewrite
#RUN a2enmod ssl
#RUN a2enmod mime
#RUN chmod +x /etc/apache2/envvars

RUN useradd deegraph -d /store/deegraph



# copy over the nginx config
RUN rm /etc/nginx/sites-enabled/*
RUN rm /etc/nginx/sites-available/*
COPY Config/nginx /etc/nginx
RUN for f in /etc/nginx/sites-available/*; do \
      ln -s "$f" /etc/nginx/sites-enabled/$(basename "$f"); \
    done

# copy over the php config
COPY Config/php.ini /etc/php/php.ini
#RUN export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`; mv /etc/php/php.ini.tmp /etc/php/$PHP_VER/apache2/php.ini;

# copy over composer config
#COPY Auxilium/composer.json /srv/Auxilium/composer.json
#COPY Auxilium/composer.lock /srv/Auxilium/composer.lock

COPY Auxilium /srv/Auxilium

# set web perms on the auxilium directory
RUN chown www-data:www-data /srv -R

# cd & su
WORKDIR /srv/Auxilium
USER www-data

# install composer packages
ENV COMPOSER_HOME=/tmp/composer
RUN mkdir -p $COMPOSER_HOME
RUN composer config allow-plugins.endroid/installer true
RUN composer install

COPY ConfigTemplates/Environment-Docker.php /srv/Auxilium/Configuration/Configuration/Environment.php

USER root
WORKDIR /app

COPY Scripts/new-keys.php /app/new-keys.php

COPY Config/mariadb-50-server.cnf /etc/mysql/mariadb.conf.d/50-server.cnf
COPY Config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY bin/deegraph.jar /app/deegraph.jar
COPY Config/launch.sh /app/launch.sh

RUN chmod +x /app/deegraph.jar
RUN chmod +x /app/launch.sh

RUN mkdir /var/EphemeralCredentialsStore
RUN chown www-data:www-data /var/EphemeralCredentialsStore -R

RUN mkdir /store
RUN chown www-data:www-data /store -R

RUN usermod -d /var/lib/mysql/ mysql

ENTRYPOINT ["/usr/bin/supervisord"]

STOPSIGNAL SIGQUIT
