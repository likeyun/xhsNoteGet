FROM php:8.4-apache
RUN docker-php-ext-install -j"$(nproc)" opcache && a2enmod headers rewrite && apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev && docker-php-ext-install curl && rm -rf /var/lib/apt/lists/*
COPY apache.conf /etc/apache2/conf-available/xhs-security.conf
RUN a2enconf xhs-security
