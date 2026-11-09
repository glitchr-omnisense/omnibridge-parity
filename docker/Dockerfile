# The hosts' PHP: glitchr-studio/ci's image (PHP 8.4, Composer, every extension omnisense/omnibase asks for)
# with pdo_mysql, the one it lacks, for the shared MySQL database.
FROM ghcr.io/glitchr-studio/php:8.4
RUN docker-php-ext-install pdo_mysql
WORKDIR /srv/parity
