FROM php:5.6-apache

RUN sed -i -e 's/deb.debian.org/archive.debian.org/g' \
           -e 's|security.debian.org|archive.debian.org/|g' \
           -e '/stretch-updates/d' /etc/apt/sources.list

RUN apt-get update
RUN apt-get install --yes --force-yes git cron g++ gettext libicu-dev openssl libc-client-dev libkrb5-dev  libxml2-dev libfreetype6-dev libgd-dev libmcrypt-dev bzip2 libbz2-dev libtidy-dev libcurl4-openssl-dev libz-dev libmemcached-dev libxslt-dev mcrypt libldap2-dev libxml2-dev locales-all

RUN docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap zip xsl xmlrpc

RUN apt-get install --yes libpq-dev

RUN docker-php-ext-install mysql pgsql
RUN docker-php-ext-enable mysql pgsql

RUN docker-php-ext-configure gd --with-freetype-dir=/usr --with-jpeg-dir=/usr --with-png-dir=/usr
RUN docker-php-ext-install gd

RUN pear install radius Auth_RADIUS Crypt_CHAP

RUN pear upgrade
RUN pear install --onlyreqdeps Cache_Lite
RUN pear install --onlyreqdeps HTML_Safe-beta
RUN pear install --onlyreqdeps Image_graph-alpha

RUN docker-php-ext-install gettext mcrypt

RUN echo "extension=radius.so" > /usr/local/etc/php/conf.d/docker-php-ext-radius.ini

RUN a2enmod rewrite
